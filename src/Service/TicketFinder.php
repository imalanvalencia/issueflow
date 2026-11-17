<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\TicketCatalogInterface;
use App\Entity\Ticket;
use App\Exception\InvalidTicketCodeException;
use App\Exception\TicketNotFoundException;

final class TicketFinder
{
    public function __construct(
        private readonly TicketCatalogInterface $ticketCatalog,
    ) {}

    public function findOrFail(string $code): Ticket
    {
        // Validar el formato del código de la incidencia
        if (preg_match('/^INC-\d{4}$/', $code) !== 1) {
            // Si el código no tiene el formato correcto, lanzo una excepción
            throw new InvalidTicketCodeException();
        }

        // Buscar la incidencia en la base de datos usando el TicketCatalog
        // El operador ?? devuelve el ticket si se encuentra, o lanza una excepción si no se encuentra
        return $this->ticketCatalog->findByCode($code)
            ?? throw new TicketNotFoundException();
    }
}
