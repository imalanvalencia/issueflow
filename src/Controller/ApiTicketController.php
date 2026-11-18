<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\Model\TicketCreatePayload;
use App\Api\Model\TicketListQuery;
use App\Api\Mapper\TicketResponseMapper;
use App\Api\Model\TicketUpdatePayload;
use App\Form\Model\TicketSearchCriteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Ticket;
use App\Service\TicketPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

final class ApiTicketController extends AbstractController
{
    public function __construct(
        private readonly TicketPaginator $ticketPaginator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TicketResponseMapper $ticketResponseMapper,
    ) {}
    /**
     * Esta ruta devuelve un listado de las incidencias
     * Está petición se hace con el protocolo HTTP, aunque venga de una app móvil
     * Ejemplo de url: miappdeincidencias.com/api/tickets?query=factura&category=1&priority=urgent&page=1
     */
    #[Route('/api/tickets', name: 'app_api_ticket_index', methods: ['GET'], format: 'json')]
    public function index(
        #[MapQueryString(
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST,
            mapWhenEmpty: true,
        )]
        TicketListQuery $filters,
    ): JsonResponse
    {
        $criteria = new TicketSearchCriteria();
        $criteria->query = $filters->query;
        $criteria->priority = $filters->priorityEnum();
        $criteria->status = $filters->statusEnum();

        /**
         * Usar el servicio TicketPaginator para obtener la página
         * adecuada
         */
        $ticketPage = $this->ticketPaginator->getTicketPage($criteria, $filters->page);

        $items = $this->ticketResponseMapper->mapCollection($ticketPage->getItems());

        return $this->json([
            'tickets' => $items
        ]);
    }

    /**
     * Ruta para crear una incidencia desde JSON
     */
    #[Route('/api/tickets', name: 'app_api_ticket_create', methods: ['POST'])]

    /**
     * MapRequestPayload mapea los datos recibidos en el formato aceptado (JSON)
     * y los convierte a un objeto de la clase indicada (TicketCreatePayload),
     * aplica previamente las validaciones de este objeto si existen, si la
     * validación falla devuelve el error establecido en validationFailedStatusCode
     */
    public function create(
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        TicketCreatePayload $payload,
    ): JsonResponse
    {
        // Si los datos son válidos los procesamos

        // Creo un nuevo ticket con los datos del payload
        $ticket = new Ticket(
            code: $payload->code,
            title: $payload->title,
            priority: $payload->priority,
            status: $payload->status,
        );

        // persisto el ticket en la base de datos

        // agrega la tarea a la unidad de trabajo
        $this->entityManager->persist($ticket);
        // ejecuta las tareas que esten en la unidad de trabajo
        $this->entityManager->flush();

        /**
         * Respondo al cliente con la data creada, código 201, cabeceras con la información de la ruta de creación
         */
        return $this->json(
            [
                'ticket' => $this->ticketResponseMapper->map($ticket),
            ],
            Response::HTTP_CREATED,
            [
                'Location' => $this->generateUrl('app_api_ticket_show', ['code' => $ticket->getCode()])
            ]
        );
    }


    /**
     * Ruta para solicitar al API un ticket por su código
     * El resolver TicketValueResolver se encarga de buscar el ticket en la base de datos y 
     * pasarlo como argumento a este método
     */
    #[Route('/api/tickets/{code}', name: 'app_api_ticket_show', methods: ['GET'])]
    public function show(Ticket $ticket): JsonResponse
    {
        return $this->json([
            'ticket' => $this->ticketResponseMapper->map($ticket),
        ]);
    }

    #[Route('/api/tickets/{code}', name: 'app_api_ticket_update', methods: ['PATCH'])]
    public function update(
        Ticket $ticket,
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        TicketUpdatePayload $payload,
    ): JsonResponse {
        if ($payload->title !== null) {
            $ticket->setTitle($payload->title);
        }

        if (($priority = $payload->priorityEnum()) !== null) {
            $ticket->setPriority($priority);
        }

        if (($status = $payload->statusEnum()) !== null) {
            $ticket->setStatus($status);
        }

        $this->entityManager->flush();

        return $this->json([
            'ticket' => $this->ticketResponseMapper->map($ticket),
        ]);
    }

    #[Route('/api/tickets/{code}', name: 'app_api_ticket_delete', methods: ['DELETE'])]
    public function delete(Ticket $ticket): Response
    {
        $this->entityManager->remove($ticket);
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

}
