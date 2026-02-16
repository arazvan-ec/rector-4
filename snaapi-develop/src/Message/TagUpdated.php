<?php

declare(strict_types=1);

namespace App\Message;

final readonly class TagUpdated
{
    public function __construct(
        public string $id,
    ) {}
}
