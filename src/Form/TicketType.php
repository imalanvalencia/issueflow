<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\User;
use App\Form\Model\TicketCreateData;
use App\Model\TicketPriority;
use App\Model\TicketStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Describe los campos necesarios del formulario para crear un Ticket
 * El Form Type no guarda nada en la base de datos, simplemente define
 * como se transforman, validan a nivel de cliente y como se presentan
 * los datos del formulario
 */
final class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [ // TextType indica que es un campo de texto en el formulario
                'label' => 'Código', // Etiqueta del campo que se muestra al usuario
                'attr' => [ // Corresponden a atributos HTML
                    'placeholder' => 'INC-1234', // Sugerencia de entrada
                    'pattern' => 'INC-[0-9]{4}', // Patrón de entrada esperado
                    'maxlength' => 8 // Longitud máxima aceptada
                ]
            ])
            ->add('title', TextType::class, [
                'label' => 'Título',
                'attr' => [
                    'maxlength' => 180,
                ]
            ])
            ->add('priority', EnumType::class, [ // EnumType construye un selector basado en un enum PHP
                'class' => TicketPriority::class, // Indicamos el enum que se va a usar para construir el selector
                'label' => 'Prioridad',
                'choice_label' => 'label' // Seleccionamos label para que sea la información que se muestra en las opciones del selector
            ])
            ->add('status', EnumType::class, [
                'class' => TicketStatus::class,
                'label' => 'Estado',
                'choice_label' => 'label'
            ])
            ->add('category', EntityType::class, [ // EntityType construye un selector basado en una clase PHP
                'class' => Category::class, // Indicamos la clase que se va a usar para construir el selector
                'label' => 'Categoría',
                'choice_label' => 'name', // Indicamos el atributo de la clase que se mostrará en las opciones del selector
                'required' => false, // Este campo es opcional, no es requerido
                'placeholder' => 'Sin asignar'
            ])
            ->add('assignee', EntityType::class, [
                'class' => User::class,
                'label' => 'Asignado a',
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Sin asignar'
            ])
            ->add('save', SubmitType::class, [ // SubmitType crea un botón de envío para el formulario
                'label' => 'Crear incidencia' // Etiqueta del botón
            ]);
    }

    /**
     * Configura el dto que se usará para mapear los datos del formulario a la entidad Ticket
     * Esto permite que Symfony Form haga el mapeo automáticamente, y que aplique las validaciones definidas en el dto
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TicketCreateData::class,
        ]);
    }
}