<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\Model\TicketCreatePayload;
use App\Api\Model\TicketListQuery;
use App\Api\Model\TicketUpdatePayload;
use App\Form\Model\TicketSearchCriteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Contract\TicketCatalogInterface;
use App\Entity\Ticket;
use App\Service\TicketPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

final class ApiTicketController extends AbstractController
{
    public function __construct(
        private readonly TicketCatalogInterface $ticketCatalog,
        private readonly TicketPaginator $ticketPaginator,
        private readonly EntityManagerInterface $entityManager,
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

        /**
         * Antes de enviar la respuesta al usuario, necesito convertir el objeto WindowPage
         * al formato JSON.
         */
        // El método getItems() devuelve el listado de objetos Ticket
        $tickets = $ticketPage->getItems();

        /**
         * Voy a mapear cada ticket de $tickets de objeto Ticket a JSON
         * La función array_map() mapea todos los elementos de un array dado, aplicando
         * para ello aplica un callback a cada uno de los elementos.
         * El primer argumento es el callback que aplica el mapeo
         * El segundo argumento es el array a mapear
         */
        $items = array_map(
            fn(Ticket $ticket): array => $this->ticketToArray($ticket),
            $tickets
        );

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
                'ticket' => $this->ticketToArray($ticket),
            ],
            Response::HTTP_CREATED,
            [
                'Location' => $this->generateUrl('app_api_ticket_show', ['code' => $ticket->getCode()])
            ]
        );
    }


    /**
     * Ruta para solicitar al API un ticket por su código
     */
    #[Route('/api/tickets/{code}', name: 'app_api_ticket_show', methods: ['GET'])]
    public function show(string $code): JsonResponse
    {
        // En API diferenciamos un formato inválido (400) de un formato válido pero inexistente (404)

        // Verificamos que el formato sea válido
        if (preg_match('/^INC-\d{4}$/', $code) !== 1) {
            return $this->json(
                [
                    'error' => [
                        'code' => 'invalid_ticket_code',
                        'message' => 'El código debe tener el formato INC-0000'
                    ]
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Si el code tiene un formato válido, buscamos el ticket en la base de datos
        $ticket = $this->ticketCatalog->findByCode($code);

        // Si el ticket no existe, devolvemos un error
        if ($ticket === null) {
            return $this->json(
                [
                    'error' => [
                        'code' => 'ticket_not_found',
                        'message' => 'La incidencia solicitada no existe'
                    ]
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        // Si el ticket existe devolvemos el ticket en el formato correspondiente
        return $this->json([
            'ticket' => $this->ticketToArray($ticket),
        ]);
    }

    #[Route('/api/tickets/{code}', name: 'app_api_ticket_update', methods: ['PATCH'])]
    public function update(
        string $code,
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        TicketUpdatePayload $payload,
    ): JsonResponse {
        if (preg_match('/^INC-\\d{4}$/', $code) !== 1) {
            return $this->json(
                [
                    'error' => [
                        'code' => 'invalid_ticket_code',
                        'message' => 'El código debe tener el formato INC-0000',
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $ticket = $this->ticketCatalog->findByCode($code);

        if ($ticket === null) {
            return $this->json(
                [
                    'error' => [
                        'code' => 'ticket_not_found',
                        'message' => 'La incidencia solicitada no existe',
                    ],
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

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
            'ticket' => $this->ticketToArray($ticket),
        ]);
    }

    #[Route('/api/tickets/{code}', name: 'app_api_ticket_delete', methods: ['DELETE'])]
    public function delete(string $code): Response
    {
        if (preg_match('/^INC-\d{4}$/', $code) !== 1) {
            return $this->json(
                [
                    'error' => [
                        'code' => 'invalid_ticket_code',
                        'message' => 'El código debe tener el formato INC-0000',
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $ticket = $this->ticketCatalog->findByCode($code);

        if ($ticket === null) {
            return $this->json(
                [
                    'error' => [
                        'code' => 'ticket_not_found',
                        'message' => 'La incidencia solicitada no existe',
                    ],
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->entityManager->remove($ticket);
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function ticketToArray(Ticket $ticket): array
    {
        return [
            'code' => $ticket->getCode(),
            'title' => $ticket->getTitle(),
            'priority' => $ticket->getPriority()->value,
            'status' => $ticket->getStatus()->value,
        ];
    }
}