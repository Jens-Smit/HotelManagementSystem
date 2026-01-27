<?php
declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AvailabilityCheckRequest
{
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $startDate;

    #[Assert\NotBlank]
    #[Assert\Date]
    #[Assert\GreaterThan(propertyPath: 'startDate')]
    public string $endDate;
}