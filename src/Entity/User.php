<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Usuario mínimo de IssueFlow para asignar tickets.
 * Para utilizar User como nuestra entidad de autenticación, esta
 * debe implementar UserInterface y PasswordAuthenticatedUserInterface
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * Identificador técnico (id) de base de datos.
     * Comienza con un valor null porque una entidad recién
     * creada aún no ha sido insertada.
     * El valor de id será asignado al momento de persistirla
     */
    #[ORM\Id] // Indica que corresponde a la clave primaria (PK: Primary key)
    #[ORM\GeneratedValue] // Indica que es un valor autogenerado
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])] // Si no especifico nombre de la columna, esta toma el nombre del atributo
    private ?int $id = null;

    /**
     * Guardamos siempre el HASH de la contraseña, nunca la contraseña plana
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $name,

        #[ORM\Column(length: 180, unique: true)]
        private string $email,
    )
    {
        // Normalizo el nombre quitando los espacios en blanco al principio y al final
        $normalizedName = trim($name);

        // Normalizo el email quitando los espacios en blanco al principio y al final y poniendo todo en minúscula
        $normalizedEmail = strtolower(trim($email));

        // Si el nombre normalizado está vacío lanzo un error
        if ($normalizedName === '') {
            throw new InvalidArgumentException('El nombre del usuario no puede estar vacío');
        }

        // Si el email normalizado no tiene un formato válido lanzo un error
        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El email no es válido');
        }

        $this->name = $normalizedName;
        $this->email = $normalizedEmail;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Implementamos los métodos de las interfaces UserInterface y PasswordAuthenticatedUserInterface
     */

    // Método getUserIdentifier() que devuelve el identificador público (username / nombre de usuario) para ingreso
    public function getUserIdentifier(): string
    {
        return $this->email;    
    }

    // Método getRoles() que devuelve el listado de roles de un usuario
    public function getRoles(): array
    {
        // Por los momentos todos los usuarios solo tienen el rol base
        return ['ROLE_USER'];
    }

    // Método getPassword() que devuelve la contraseña del usuario
    public function getPassword(): ?string
    {
        return $this->password;
    }

    // Método setPassword() que recibe un hash y lo asigna al password
    public function setPassword(string $hash): void
    {
        $this->password = $hash;
    }
}