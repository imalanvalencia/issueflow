<?php

declare(strict_types=1);

namespace App\Api\Model;

final readonly class TicketResponse
{
    public function __construct(
        public string $code,
        public string $title,
        public string $priority,
        public string $status,
    ) {}
}
