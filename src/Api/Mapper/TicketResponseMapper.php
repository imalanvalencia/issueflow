<?php

declare(strict_types=1);

namespace App\Api\Mapper;

use App\Api\Model\TicketResponse;
use App\Entity\Ticket;

final class TicketResponseMapper
{
    public function map(Ticket $ticket): TicketResponse
    {
        return new TicketResponse(
            code: $ticket->getCode(),
            title: $ticket->getTitle(),
            priority: $ticket->getPriority()->value,
            status: $ticket->getStatus()->value,
        );
    }

    /**
     * @param Ticket[] $tickets
     * @return TicketResponse[]
     */
    public function mapCollection(array $tickets): array
    {
        return array_map(fn (Ticket $ticket): TicketResponse => $this->map($ticket), $tickets);
    }
}
