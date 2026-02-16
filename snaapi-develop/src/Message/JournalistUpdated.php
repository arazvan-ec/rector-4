<?php

declare(strict_types=1);

namespace App\Message;

final readonly class JournalistUpdated
{
    public function __construct(
        public string $id,
    ) {}
}
