<?php

declare(strict_types=1);

namespace App\Tests\Api\Mapper;

use App\Api\Mapper\TicketResponseMapper;
use App\Api\Model\TicketResponse;
use App\Entity\Ticket;
use App\Model\TicketPriority;
use App\Model\TicketStatus;
use PHPUnit\Framework\TestCase;

final class TicketResponseMapperTest extends TestCase
{
    public function test_it_maps_a_ticket_to_its_response_dto(): void
    {
        $ticket = new Ticket(
            code: 'INC-1001',
            title: 'Error de autenticación',
            priority: TicketPriority::High,
            status: TicketStatus::Open,
        );

        $response = (new TicketResponseMapper())->map($ticket);

        self::assertInstanceOf(TicketResponse::class, $response);
        self::assertSame('INC-1001', $response->code);
        self::assertSame('Error de autenticación', $response->title);
        self::assertSame('high', $response->priority);
        self::assertSame('open', $response->status);
    }

    public function test_it_maps_a_collection_of_tickets(): void
    {
        $tickets = [
            new Ticket('INC-1001', 'Primer ticket', TicketPriority::High, TicketStatus::Open),
            new Ticket('INC-1002', 'Segundo ticket', TicketPriority::Low, TicketStatus::Closed),
        ];

        $responses = (new TicketResponseMapper())->mapCollection($tickets);

        self::assertEquals([
            new TicketResponse('INC-1001', 'Primer ticket', 'high', 'open'),
            new TicketResponse('INC-1002', 'Segundo ticket', 'low', 'closed'),
        ], $responses);
    }

    public function test_it_maps_an_empty_collection_to_an_empty_array(): void
    {
        self::assertSame([], (new TicketResponseMapper())->mapCollection([]));
    }
}
