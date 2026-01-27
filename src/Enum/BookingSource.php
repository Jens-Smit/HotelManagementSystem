<?php

namespace App\Enum;

enum BookingSource: string
{
    case WEBSITE = 'website';
    case BOOKING_COM = 'booking_com';
    case HOMEAWAY = 'homeaway';
    case MANUAL = 'manual';
}