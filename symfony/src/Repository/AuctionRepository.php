<?php

namespace App\Repository;

use App\Entity\Auction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Auction>
 */
class AuctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Auction::class);
    }

    /**
     * @return Auction[]
     */
    public function findPaginated(int $page, int $limit, ?string $status = null, ?string $caseNo = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.startsAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($status !== null) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }
        if ($caseNo !== null) {
            $qb->andWhere('a.caseNo LIKE :caseNo')->setParameter('caseNo', '%' . $caseNo . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countFiltered(?string $status = null, ?string $caseNo = null): int
    {
        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)');

        if ($status !== null) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }
        if ($caseNo !== null) {
            $qb->andWhere('a.caseNo LIKE :caseNo')->setParameter('caseNo', '%' . $caseNo . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
