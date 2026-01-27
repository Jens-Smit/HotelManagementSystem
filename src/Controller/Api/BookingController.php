<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Request\CreateBookingRequest;
use App\Entity\Booking;
use App\Service\BookingService;
use App\Repository\BookingRepository;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/bookings')]
#[OA\Tag(name: 'Hotel Bookings')]
class BookingController extends AbstractController
{
    public function __construct(
        private BookingService $bookingService,
        private BookingRepository $bookingRepository,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer
    ) {}

    #[OA\Post(
        path: "/api/bookings",
        summary: "Neue Buchung erstellen",
        description: "Erstellt eine neue Hotelbuchung mit den angegebenen Daten",
    )]
    #[OA\RequestBody(
        required: true,
        description: "Buchungsdaten",
        content: new OA\JsonContent(
            required: ["room_id", "start_date", "end_date", "guest_name", "guest_email"],
            properties: [
                new OA\Property(property: "room_id", type: "string", format: "uuid", example: "a1b2c3d4-e5f6-7890-g1h2-i3j4k5l6m7n8"),
                new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-03-15"),
                new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-03-20"),
                new OA\Property(property: "guest_name", type: "string", example: "Max Mustermann"),
                new OA\Property(property: "guest_email", type: "string", format: "email", example: "max.mustermann@example.com"),
                new OA\Property(property: "guest_phone", type: "string", example: "+49 123 456789", nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Buchung erfolgreich erstellt",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(property: "booking_id", type: "string", format: "uuid", example: "e5f6g7h8-i9j0-k1l2-m3n4-o5p6q7r8s9t0"),
                new OA\Property(property: "booking_hash", type: "string", example: "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0"),
                new OA\Property(property: "total_price", type: "number", format: "float", example: 600.00),
                new OA\Property(property: "message", type: "string", example: "Buchung erfolgreich erstellt. Bitte überprüfen Sie Ihre E-Mails für die Bestätigung.")
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Ungültige Eingabedaten",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "error", type: "string", example: "VALIDATION_ERROR"),
                new OA\Property(property: "errors", type: "object", example: ["start_date" => "Das Startdatum muss ein gültiges Datum sein"])
            ]
        )
    )]
    #[OA\Response(
        response: 409,
        description: "Verfügbarkeitskonflikt oder Überbuchung",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "error", type: "string", example: "AVAILABILITY_CONFLICT"),
                new OA\Property(property: "message", type: "string", example: "Zimmer nicht verfügbar (externe Buchung existiert)"),
                new OA\Property(
                    property: "details",
                    type: "object",
                    properties: [
                        new OA\Property(property: "conflict_source", type: "string", example: "booking_com"),
                        new OA\Property(property: "conflict_dates", type: "array", items: new OA\Items(type: "string", example: "2026-03-15"))
                    ]
                ),
                new OA\Property(
                    property: "suggested_alternatives",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "room_id", type: "string", format: "uuid"),
                            new OA\Property(property: "room_name", type: "string"),
                            new OA\Property(property: "price_difference", type: "string")
                        ]
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Nicht autorisiert"
    )]
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // 1. Parse and validate request
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse([
                'success' => false,
                'error' => 'INVALID_JSON',
                'message' => 'Invalid JSON payload',
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = new CreateBookingRequest();
        $dto->roomId = $data['room_id'] ?? '';
        $dto->startDate = $data['start_date'] ?? '';
        $dto->endDate = $data['end_date'] ?? '';
        $dto->guestName = $data['guest_name'] ?? '';
        $dto->guestEmail = $data['guest_email'] ?? '';
        $dto->guestPhone = $data['guest_phone'] ?? null;

        // 2. Validate
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'errors' => $errorMessages,
            ], Response::HTTP_BAD_REQUEST);
        }

        // 3. Create booking via service
        $response = $this->bookingService->createBooking($dto);

        // 4. Return appropriate HTTP status
        $statusCode = match($response->error) {
            'ROOM_NOT_FOUND' => Response::HTTP_NOT_FOUND,
            'INTERNAL_CONFLICT', 'EXTERNAL_CONFLICT', 'CONCURRENT_MODIFICATION' => Response::HTTP_CONFLICT,
            'AVAILABILITY_CHECK_FAILED' => Response::HTTP_SERVICE_UNAVAILABLE,
            'SYSTEM_ERROR', 'TRANSACTION_FAILED' => Response::HTTP_INTERNAL_SERVER_ERROR,
            default => $response->success ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST,
        };

        return new JsonResponse($response->toArray(), $statusCode);
    }

    #[OA\Get(
        path: "/api/bookings/{hash}",
        summary: "Buchung nach Hash abrufen",
        description: "Gibt eine Buchung anhand ihres eindeutigen Hash-Wertes zurück",
    )]
    #[OA\Parameter(
        name: "hash",
        in: "path",
        description: "Eindeutiger Hash der Buchung",
        required: true,
        schema: new OA\Schema(type: "string")
    )]
    #[OA\Response(
        response: 200,
        description: "Buchung erfolgreich abgerufen",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(
                    property: "booking",
                    ref: new Model(type: Booking::class, groups: ["api"])
                )
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Buchung nicht gefunden",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "error", type: "string", example: "BOOKING_NOT_FOUND"),
                new OA\Property(property: "message", type: "string", example: "Buchung nicht gefunden")
            ]
        )
    )]
    #[Route('/{hash}', methods: ['GET'])]
    public function getByHash(string $hash): JsonResponse
    {
        $booking = $this->bookingRepository->findByHash($hash);

        if (!$booking) {
            return new JsonResponse([
                'success' => false,
                'error' => 'BOOKING_NOT_FOUND',
                'message' => 'Booking not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'booking' => $booking,
        ], Response::HTTP_OK, [], ['groups' => ['api']]);
    }

    #[OA\Delete(
        path: "/api/bookings/{hash}",
        summary: "Buchung stornieren",
        description: "Storniert eine bestehende Buchung anhand ihres Hash-Wertes",
    )]
    #[OA\Parameter(
        name: "hash",
        in: "path",
        description: "Eindeutiger Hash der Buchung",
        required: true,
        schema: new OA\Schema(type: "string")
    )]
    #[OA\Response(
        response: 200,
        description: "Buchung erfolgreich storniert",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(property: "message", type: "string", example: "Buchung erfolgreich storniert")
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Stornierung nicht möglich (z.B. zu kurzfristig)",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "error", type: "string", example: "CANCELLATION_TOO_LATE"),
                new OA\Property(property: "message", type: "string", example: "Stornierung muss mindestens 24 Stunden vor Check-In erfolgen")
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Buchung nicht gefunden",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "error", type: "string", example: "BOOKING_NOT_FOUND")
            ]
        )
    )]
    #[Route('/{hash}', methods: ['DELETE'])]
    public function cancel(string $hash): JsonResponse
    {
        $booking = $this->bookingRepository->findByHash($hash);

        if (!$booking) {
            return new JsonResponse([
                'success' => false,
                'error' => 'BOOKING_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if cancellation is allowed (e.g., not within 24h of check-in)
        $now = new \DateTime();
        $checkInDate = $booking->getStartDate();
        $hoursDiff = ($checkInDate->getTimestamp() - $now->getTimestamp()) / 3600;

        if ($hoursDiff < 24) {
            return new JsonResponse([
                'success' => false,
                'error' => 'CANCELLATION_TOO_LATE',
                'message' => 'Cancellation must be at least 24 hours before check-in',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($booking->getStatus() === 'cancelled') {
            return new JsonResponse([
                'success' => false,
                'error' => 'ALREADY_CANCELLED',
                'message' => 'Booking already cancelled',
            ], Response::HTTP_BAD_REQUEST);
        }

        $booking->setStatus('cancelled');
        $this->bookingRepository->save($booking, true);

        return new JsonResponse([
            'success' => true,
            'message' => 'Booking cancelled successfully',
        ]);
    }
}