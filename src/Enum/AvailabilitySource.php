<?php

namespace App\Enum;

enum AvailabilitySource: string
{
    case BOOKING_COM = 'booking_com';
    case HOMEAWAY = 'homeaway';
    case INTERNAL = 'internal';
}