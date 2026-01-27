<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Room;
use App\Repository\RoomRepository;
use App\Service\AvailabilityService;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/rooms')]
#[OA\Tag(name: 'Hotel Rooms')]
class RoomController extends AbstractController
{
    public function __construct(
        private RoomRepository $roomRepository,
        private AvailabilityService $availabilityService,
        private SerializerInterface $serializer
    ) {}

    #[OA\Parameter(name: "page", in: "query", description: "Seitenzahl", schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", in: "query", description: "Ergebnisse pro Seite", schema: new OA\Schema(type: "integer", default: 10))]
    #[OA\Parameter(name: "is_active", in: "query", description: "Filter nach Status", schema: new OA\Schema(type: "boolean"))]
    #[OA\Response(
        response: 200,
        description: "Erfolgreiche Antwort mit der Zimmerliste",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "data", type: "array", items: new OA\Items(ref: new Model(type: Room::class, groups: ["api"]))),
                new OA\Property(property: "meta", type: "object", properties: [
                    new OA\Property(property: "current_page", type: "integer"),
                    new OA\Property(property: "items_per_page", type: "integer"),
                    new OA\Property(property: "total_items", type: "integer"),
                    new OA\Property(property: "total_pages", type: "integer")
                ])
            ]
        )
    )]
    #[Route('', name: 'api_rooms_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $isActive = $request->query->getBoolean('is_active', true);

        $rooms = $this->roomRepository->findByFilters($isActive, $page, $limit);
        $total = $this->roomRepository->countByFilters($isActive);

        return $this->json([
            'data' => $rooms,
            'meta' => [
                'current_page' => $page,
                'items_per_page' => $limit,
                'total_items' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ], Response::HTTP_OK, [], ['groups' => ['api']]);
    }

    #[OA\Parameter(name: "id", in: "path", description: "UUID des Zimmers", schema: new OA\Schema(type: "string", format: "uuid"))]
    #[OA\Response(
        response: 200, 
        description: "Zimmerdetails", 
        content: new OA\JsonContent(ref: new Model(type: Room::class, groups: ["api"]))
    )]
    #[OA\Response(response: 404, description: "Zimmer nicht gefunden")]
    #[Route('/{id}', name: 'api_rooms_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json([
                'success' => false,
                'error' => 'ROOM_NOT_FOUND',
                'message' => 'Zimmer nicht gefunden'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($room, Response::HTTP_OK, [], ['groups' => ['api']]);
    }

    
    #[OA\Parameter(name: "checkIn", in: "query", required: true, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "checkOut", in: "query", required: true, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "capacity", in: "query", schema: new OA\Schema(type: "integer", minimum: 1, default: 1))]
    #[OA\Response(response: 200, description: "Verfügbare Zimmer gefunden", content: new OA\JsonContent(type: "object", properties: 
    [
        new OA\Property(property: "success", type: "boolean"),
        new OA\Property(property: "rooms", type: "array", items: new OA\Items(type: "object", properties: [
            new OA\Property(property: "id", type: "string"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "total_price", type: "number")
        ]))
    ]))]
    #[Route('/available', name: 'api_rooms_available', methods: ['GET'])]
    public function listAvailableRooms(Request $request): JsonResponse
    {
        $checkIn = $request->query->get('checkIn');
        $checkOut = $request->query->get('checkOut');
        $capacity = $request->query->getInt('capacity', 1);

        if (!$checkIn || !$checkOut) {
            return $this->json([
                'success' => false,
                'error' => 'MISSING_DATES',
                'message' => 'checkIn und checkOut Parameter sind erforderlich'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTimeImmutable($checkIn);
            $endDate = new \DateTimeImmutable($checkOut);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'INVALID_DATE_FORMAT',
                'message' => 'Daten müssen im Format YYYY-MM-DD sein'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($endDate <= $startDate) {
            return $this->json([
                'success' => false,
                'error' => 'INVALID_DATE_RANGE',
                'message' => 'Check-Out Datum muss nach Check-In Datum liegen'
            ], Response::HTTP_BAD_REQUEST);
        }

        $availableRooms = $this->roomRepository->findAvailableRoomsByCapacity(
            $capacity,
            $startDate,
            $endDate
        );

        $nights = $startDate->diff($endDate)->days;

        return $this->json([
            'success' => true,
            'rooms' => array_map(function($room) use ($nights) {
                $basePrice = $room->getBasePrice();
                $totalPrice = bcmul($basePrice, (string)$nights, 2);

                return [
                    'id' => $room->getId(),
                    'name' => $room->getName(),
                    'capacity' => $room->getCapacity(),
                    'price_per_night' => $basePrice,
                    'total_price' => $totalPrice,
                ];
            }, $availableRooms),
            'search_criteria' => [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'capacity' => $capacity,
                'nights' => $nights,
            ],
        ]);
    }
}