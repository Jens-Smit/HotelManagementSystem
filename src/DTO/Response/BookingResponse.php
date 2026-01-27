<?php
declare(strict_types=1);

namespace App\DTO\Response;

class BookingResponse
{
    /**
     * @param array<string, mixed> $details
     * @param array<string, mixed> $suggestedAlternatives
     */
    public function __construct(
        public bool $success,
        public ?string $bookingId = null,
        public ?string $bookingHash = null,
        public ?float $totalPrice = null,
        public ?string $message = null,
        public ?string $error = null,
        public array $details = [],
        public array $suggestedAlternatives = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'booking_id' => $this->bookingId,
            'booking_hash' => $this->bookingHash,
            'total_price' => $this->totalPrice,
            'message' => $this->message,
            'error' => $this->error,
            'details' => $this->details,
            'suggested_alternatives' => $this->suggestedAlternatives,
        ], fn($value) => $value !== null);
    }
}