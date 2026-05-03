<?php

namespace App\Controller;

use App\Dto\Request\GetAuctionsRequestDto;
use App\Dto\Request\UpdateAuctionRequestDto;
use App\Dto\Response\GetAssetResponseDto;
use App\Dto\Response\GetAuctionResponseDto;
use App\Dto\Response\GetAuctionsResponseDto;
use App\Dto\Response\PaginableResponseDto;
use App\Dto\Xml\AuctionXml;
use App\Entity\Asset;
use App\Entity\Auction;
use App\Repository\AuctionRepository;
use App\Service\XmlParserService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/auctions')]
class AuctionsController extends AbstractController
{
    public function __construct(
        private AuctionRepository $auctionRepository,
        private XmlParserService $xmlParserService,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/', name: 'auctions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAll(
        #[MapQueryString()] ?GetAuctionsRequestDto $requestDto,
    ): JsonResponse
    {
        $auctions = $this->auctionRepository->findPaginated($requestDto?->page ?? 1, $requestDto?->limit ?? 10, $requestDto?->status, $requestDto?->case_no);

        $auctions = array_map(fn($auction) => new GetAuctionsResponseDto(
            $auction->getId(),
            $auction->getCaseNo(),
            $auction->getDebtor(),
            $auction->getStartsAt()->format(\DateTimeInterface::ATOM),
            $auction->getStatus(),
            count($auction->getAssets())
        ), $auctions);

        $response = new PaginableResponseDto($auctions);

        return $this->json($response);
    }

    #[Route('/{id}', name: 'auction', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getOne(string $id): JsonResponse
    {
        $auction = $this->auctionRepository->find($id);

        if (!$auction) {
            throw new NotFoundHttpException();
        }

        $assets = array_map(fn($asset) => new GetAssetResponseDto(
            $asset->getId(),
            $asset->getTitle(),
            $asset->getDescription(),
            $asset->getMinPrice(),
            $asset->getCategory() ?? '',
        ), $auction->getAssets()->toArray() ?? []);

        $response = new GetAuctionResponseDto(
            $auction->getId(),
            $auction->getStatus(),
            $auction->getCaseNo(),
            $auction->getDebtor(),
            $auction->getStartsAt()->format(\DateTimeInterface::ATOM),
            $assets
        );

        return $this->json($response);
    }

    #[Route('/import', name: 'import_auctions', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file || !$file->isValid()) {
            return $this->json(['error' => 'Invalid file upload'], 400);
        }

        try {
            $dto = $this->xmlParserService->parse(
                file_get_contents($file->getRealPath()),
                AuctionXml::class
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        $auction = new Auction();
        $auction->setCaseNo($dto->caseNo);
        $auction->setDebtor($dto->debtor);
        $auction->setStartsAt($this->parseDateTime($dto->startsAt));

        foreach ($dto->assets as $assetDto) {
            $asset = new Asset();
            $asset->setTitle($assetDto->title);
            $asset->setDescription($assetDto->description);
            $asset->setMinPrice((int) $assetDto->minPriceRaw);
            $asset->setCategory($assetDto->category);
            $auction->addAsset($asset);
        }

        $this->entityManager->persist($auction);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => "Az esetszám már létezik: {$dto->caseNo}"], 409);
        }

        return $this->json(['id' => $auction->getId()], 201);
    }

    #[Route('/{id}', name: 'delete_auction', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $id): JsonResponse
    {
        $auction = $this->auctionRepository->find($id);

        if (!$auction) {
            throw new NotFoundHttpException();
        }

        $this->entityManager->remove($auction);
        $this->entityManager->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}', name: 'update_auction', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(
        #[MapRequestPayload()] UpdateAuctionRequestDto $dto,
        string $id): JsonResponse
    {
        $auction = $this->auctionRepository->find($id);

        if (!$auction) {
            throw new NotFoundHttpException();
        }

        $transitionResult = $auction->canTransitionTo($dto->status);
        if (!$transitionResult) {
            throw new BadRequestHttpException("Cannot transition from {$auction->getStatus()} to {$dto->status}.");
        }

        $this->entityManager->wrapInTransaction(function() use ($auction, $dto) {
            $auction->setStatus($dto->status);
        });

        return $this->json(null, 204);
    }

    private function parseDateTime(string $value): \DateTimeImmutable
    {
        $formats = [
            'Y.m.d H:i:s',
            'Y.m.d H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            \DateTimeInterface::ATOM,
            \DateTimeInterface::RFC3339_EXTENDED,
        ];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt;
            }
        }

        throw new BadRequestHttpException('Invalid date format for startsAt.');
    }
}
