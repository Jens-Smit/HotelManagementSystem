<?php
declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBookingRequest
{
    #[Assert\NotBlank(message: 'Room ID is required')]
    #[Assert\Uuid(message: 'Invalid room ID format')]
    public string $roomId;

    #[Assert\NotBlank(message: 'Start date is required')]
    #[Assert\Date(message: 'Invalid start date format')]
    public string $startDate; // Format: Y-m-d

    #[Assert\NotBlank(message: 'End date is required')]
    #[Assert\Date(message: 'Invalid end date format')]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'End date must be after start date'
    )]
    public string $endDate; // Format: Y-m-d

    #[Assert\NotBlank(message: 'Guest name is required')]
    #[Assert\Length(
        min: 2,
        max: 200,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot be longer than {{ limit }} characters'
    )]
    public string $guestName;

    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email address')]
    public string $guestEmail;

    #[Assert\Length(max: 50)]
    #[Assert\Regex(
        pattern: '/^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/',
        message: 'Invalid phone number format'
    )]
    public ?string $guestPhone = null;
}