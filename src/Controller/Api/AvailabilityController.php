<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Room;
use App\Repository\RoomRepository;
use App\Service\AvailabilityService;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\AdditionalProperties;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
#[OA\Tag(name: 'Hotel Rooms')]
class AvailabilityController extends AbstractController
{
    public function __construct(
        private RoomRepository $roomRepository,
        private AvailabilityService $availabilityService,
    ) {}

    #[OA\Get(
        path: "/api/rooms/{id}/availability",
        summary: "Verfügbarkeit eines Zimmers prüfen",
        description: "Prüft die Verfügbarkeit eines spezifischen Hotelzimmers für einen bestimmten Zeitraum",
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        description: "UUID des Hotelzimmers",
        required: true,
        schema: new OA\Schema(type: "string", format: "uuid")
    )]
    #[OA\Parameter(
        name: "start_date",
        in: "query",
        description: "Startdatum der Verfügbarkeitsprüfung (YYYY-MM-DD)",
        required: true,
        schema: new OA\Schema(type: "string", format: "date", example: "2026-03-15")
    )]
    #[OA\Parameter(
        name: "end_date",
        in: "query",
        description: "Enddatum der Verfügbarkeitsprüfung (YYYY-MM-DD)",
        required: true,
        schema: new OA\Schema(type: "string", format: "date", example: "2026-03-20")
    )]
    #[OA\Parameter(
        name: "real_time",
        in: "query",
        description: "Echtzeit-Prüfung mit externen iCal-Feeds",
        required: false,
        schema: new OA\Schema(type: "boolean", default: true)
    )]
    #[OA\Response(
        response: 200,
        description: "Verfügbarkeitsinformationen",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "available", type: "boolean", example: true),
                new OA\Property(property: "room_id", type: "string", format: "uuid"),
                new OA\Property(property: "start_date", type: "string", format: "date"),
                new OA\Property(property: "end_date", type: "string", format: "date"),
                new OA\Property(property: "price_per_night", type: "number", format: "float", example: 120.00),
                new OA\Property(property: "total_price", type: "number", format: "float", example: 600.00),
                new OA\Property(property: "last_checked", type: "string", format: "date-time"),
                new OA\Property(property: "check_sources", type: "array", items: new OA\Items(type: "string")),
                new OA\Property(
                    property: "breakdown",
                    type: "object",
                    additionalProperties: new AdditionalProperties(
                        properties: [
                            new OA\Property(property: "price", type: "number", format: "float"),
                            new OA\Property(property: "available", type: "boolean"),
                            new OA\Property(property: "source", type: "string")
                        ]
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Ungültige Anfrageparameter",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "error", type: "string", example: "MISSING_DATES"),
                new OA\Property(property: "message", type: "string", example: "start_date and end_date query parameters required")
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Zimmer nicht gefunden",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "error", type: "string", example: "ROOM_NOT_FOUND")
            ]
        )
    )]
    #[Route('/rooms/{id}/availability', methods: ['GET'])]
    public function checkAvailability(string $id, Request $request): JsonResponse
    {
        // 1. Find room
        $room = $this->roomRepository->find($id);
        if (!$room || !$room->isActive()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'ROOM_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        // 2. Parse and validate dates
        $startDateStr = $request->query->get('start_date');
        $endDateStr = $request->query->get('end_date');

        if (!$startDateStr || !$endDateStr) {
            return new JsonResponse([
                'success' => false,
                'error' => 'MISSING_DATES',
                'message' => 'start_date and end_date query parameters required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTimeImmutable((string) $startDateStr);
            $endDate = new \DateTimeImmutable((string) $endDateStr);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'INVALID_DATE_FORMAT',
                'message' => 'Dates must be in Y-m-d format',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($endDate <= $startDate) {
            return new JsonResponse([
                'success' => false,
                'error' => 'INVALID_DATE_RANGE',
                'message' => 'End date must be after start date',
            ], Response::HTTP_BAD_REQUEST);
        }

        // 3. Check availability (with real-time iCal check)
        $realTimeCheck = $request->query->getBoolean('real_time', true);
        $result = $this->availabilityService->checkRoomAvailability(
            $room,
            $startDate,
            $endDate,
            $realTimeCheck
        );

        // 4. Add pricing if available
        if ($result['available']) {
            $nights = $startDate->diff($endDate)->days;
            $basePrice = $room->getBasePrice();
            $result['price_per_night'] = $basePrice;
            $result['total_price'] = $basePrice * (int) $nights;
            $result['nights'] = $nights;
        }

        return new JsonResponse($result);
    }

   
}