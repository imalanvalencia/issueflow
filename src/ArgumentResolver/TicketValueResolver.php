<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Entity\Ticket;
use App\Service\TicketFinder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class TicketValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly TicketFinder $ticketFinder,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // Si el tipo de argumento no es Ticket, no hago nada
        if ($argument->getType() !== Ticket::class) {
            return [];
        }

        // Obtengo el valor del parámetro 'code' de la ruta, que es el identificador de la incidencia
        $code = $request->attributes->get('code', '');

        // Uso el servicio TicketFinder para buscar la incidencia en la base de datos
        yield $this->ticketFinder->findOrFail((string) $code);
    }
}
