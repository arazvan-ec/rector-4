<?php

declare(strict_types=1);

namespace App\Message;

final readonly class EditorialDeleted
{
    public function __construct(
        public string $id,
    ) {}
}
