<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Category;
use App\Entity\User;
use App\Entity\Ticket;
use App\Model\TicketPriority;
use App\Model\TicketStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Datos de entrada para crear una incidencia desde un formulario
 * Su responsabilidad es recibir los datos que Symfony Form transforma desde la
 * Request (petición), o sea, los datos que el usuario ha enviado desde el formulario
 * Los valores por defecto de los atributos corresponden a los valores iniciales
 * del formulario
 * También aplica validaciones a esos datos, y hace el mapeo hacia la entidad Ticket
 * Usamos propiedades públicas deliberadamente, este objeto es una estructura simple
 * de entrada.
 */

/**
 * Validador de Doctrine que verifica que el código no esté en uso
 */
#[UniqueEntity(
    fields: ['code'], // Que campo del registro debe ser único
    entityClass: Ticket::class, // Que entidad tengo que validar
    message: 'Ya existe una incidencia con este código', // Mensaje de error si no pasa la validación
    errorPath: 'code' // Que campo del formulario muestra el error
)]
final class TicketCreateData
{
    #[Assert\NotBlank(
        message: 'El código es obligatorio',
        normalizer: 'trim'
    )]
    #[Assert\Regex(
        pattern: '/^INC-\d{4}$/',
        message: 'El código debe tener el formato INC-0000',
        normalizer: 'trim'
    )]
    public string $code = '';

    #[Assert\NotBlank(
        message: 'El título es obligatorio',
        normalizer: 'trim'
    )]
    #[Assert\Length(
        max: 180,
        maxMessage: 'El título no puede superar los 180 caracteres',
        normalizer: 'trim'
    )]
    public string $title = '';

    public TicketPriority $priority = TicketPriority::Normal;
    public TicketStatus $status = TicketStatus::Open;
    public ?Category $category = null;
    public ?User $assignee = null;

    /**
     * Método para mapear TicketCreateData a Ticket
     */
    public function mapToTicket(): Ticket
    {
        $ticket = new Ticket(
            code: $this->code,
            title: $this->title,
            priority: $this->priority,
            status: $this->status,
        );
        $ticket->setCategory($this->category);
        $ticket->assignTo($this->assignee);

        return $ticket;
    }
}
