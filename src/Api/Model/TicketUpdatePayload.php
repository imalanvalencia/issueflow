<?php

declare(strict_types=1);

namespace App\Api\Model;

use App\Model\TicketPriority;
use App\Model\TicketStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload de entrada para PATCH /api/tickets/{code}.
 * Los campos null representan tanto los atributos omitidos como los enviados
 * explícitamente como null, que no deben modificar el ticket.
 */
final readonly class TicketUpdatePayload
{
    public function __construct(
        #[Assert\When(
            expression: 'this.title !== null',
            constraints: [
                new Assert\NotBlank(message: 'El título no puede estar vacío', normalizer: 'trim'),
                new Assert\Length(
                    max: 180,
                    maxMessage: 'El título no puede superar los 180 caracteres',
                    normalizer: 'trim',
                ),
            ],
        )]
        public ?string $title = null,

        #[Assert\When(
            expression: 'this.priority !== null',
            constraints: [new Assert\Choice(choices: ['urgent', 'high', 'normal', 'low'])],
        )]
        public ?string $priority = null,

        #[Assert\When(
            expression: 'this.status !== null',
            constraints: [new Assert\Choice(choices: ['open', 'in_progress', 'closed'])],
        )]
        public ?string $status = null,
    ) {}

    public function priorityEnum(): ?TicketPriority
    {
        return $this->priority === null ? null : TicketPriority::from($this->priority);
    }

    public function statusEnum(): ?TicketStatus
    {
        return $this->status === null ? null : TicketStatus::from($this->status);
    }
}
