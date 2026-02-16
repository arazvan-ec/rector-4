<?php

declare(strict_types=1);

namespace App\Infrastructure\Enum;

enum ServiceCriticality: string
{
    case CRITICAL = 'critical';
    case LOW = 'low';
}
