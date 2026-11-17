<?php

declare(strict_types=1);

namespace App\Api\Model;

use App\Model\TicketPriority;
use App\Model\TicketStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Parámetros de consulta para GET /api/tickets.
 */
final readonly class TicketListQuery
{
    public ?string $query;

    #[Assert\When(
        expression: 'this.priority !== null',
        constraints: [new Assert\Choice(callback: [self::class, 'priorityValues'], message: 'La prioridad indicada no es válida.')],
    )]
    public ?string $priority;

    #[Assert\When(
        expression: 'this.status !== null',
        constraints: [new Assert\Choice(callback: [self::class, 'statusValues'], message: 'El estado indicado no es válido.')],
    )]
    public ?string $status;

    public function __construct(
        ?string $query = null,
        ?string $priority = null,
        ?string $status = null,
        public ?string $page = null,
    ) {
        $this->query = $query === null ? null : trim($query);
        $this->priority = $priority === null || trim($priority) === '' ? null : trim($priority);
        $this->status = $status === null || trim($status) === '' ? null : trim($status);
    }

    /** @return list<string> */
    public static function priorityValues(): array
    {
        return array_map(static fn (TicketPriority $priority): string => $priority->value, TicketPriority::cases());
    }

    /** @return list<string> */
    public static function statusValues(): array
    {
        return array_map(static fn (TicketStatus $status): string => $status->value, TicketStatus::cases());
    }

    public function priorityEnum(): ?TicketPriority
    {
        return $this->priority === null ? null : TicketPriority::from($this->priority);
    }

    public function statusEnum(): ?TicketStatus
    {
        return $this->status === null ? null : TicketStatus::from($this->status);
    }
}
