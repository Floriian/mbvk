<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\Auction;
use App\Entity\Asset;
use App\Enum\AuditLogAction;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(Events::postPersist)]
#[AsDoctrineListener(Events::preUpdate)]
#[AsDoctrineListener(Events::preRemove)]
#[AsDoctrineListener(Events::postFlush)]
class AuditLogEventListener
{
    private array $pendingLogs = [];

    public function __construct(
        private Security $security,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->isAuditable($entity)) return;

        $this->pendingLogs[] = [
            'tableName' => $this->getTableName($entity),
            'recordId'  => $entity->getId(),
            'action'    => AuditLogAction::INSERT,
            'oldData'   => null,
            'newData'   => $this->serialize($entity),
        ];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->isAuditable($entity)) return;

        $changeSet = $args->getEntityChangeSet();

        $this->pendingLogs[] = [
            'tableName' => $this->getTableName($entity),
            'recordId'  => $entity->getId(),
            'action'    => AuditLogAction::UPDATE,
            'oldData'   => array_map(fn($c) => $c[0], $changeSet),
            'newData'   => array_map(fn($c) => $c[1], $changeSet),
        ];
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->isAuditable($entity)) return;

        $this->pendingLogs[] = [
            'tableName' => $this->getTableName($entity),
            'recordId'  => $entity->getId(),
            'action'    => AuditLogAction::DELETE,
            'oldData'   => $this->serialize($entity),
            'newData'   => null,
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingLogs)) return;

        $logs = $this->pendingLogs;
        $this->pendingLogs = [];

        $em = $args->getObjectManager();
        $user = $this->security->getUser();
        $userName = $user?->getUserIdentifier() ?? 'system';

        foreach ($logs as $log) {
            $em->getConnection()->insert('audit_log', [
                'table_name' => $log['tableName'],
                'record_id'  => $log['recordId'],
                'action'     => $log['action']->value,
                'old_data'   => isset($log['oldData']) ? json_encode($log['oldData']) : null,
                'new_data'   => isset($log['newData']) ? json_encode($log['newData']) : null,
                'user_name'  => $userName,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function isAuditable(object $entity): bool
    {
        return $entity instanceof Auction || $entity instanceof Asset;
    }

    private function getTableName(object $entity): string
    {
        return match(true) {
            $entity instanceof Auction => 'auctions',
            $entity instanceof Asset   => 'assets',
            default                    => 'unknown',
        };
    }

    private function serialize(object $entity): array
    {
        if ($entity instanceof Auction) {
            return [
                'id'         => $entity->getId(),
                'case_no'    => $entity->getCaseNo(),
                'debtor'     => $entity->getDebtor(),
                'starts_at'  => $entity->getStartsAt()->format(\DateTimeInterface::ATOM),
                'status'     => $entity->getStatus(),
                'created_at' => $entity->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        if ($entity instanceof Asset) {
            return [
                'id'          => $entity->getId(),
                'auction_id'  => $entity->getAuction()->getId(),
                'title'       => $entity->getTitle(),
                'description' => $entity->getDescription(),
                'min_price'   => $entity->getMinPrice(),
                'category'    => $entity->getCategory(),
            ];
        }

        return [];
    }
}
