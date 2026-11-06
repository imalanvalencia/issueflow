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

    /**
     * Los roles se almacenan como JSON en la base de datos.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $roles = [];

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
        // El listado de roles es igual al atributo roles si no es null, en caso contrario
        // lo inicializamos con un arreglo vacío
        $roles = $this->roles ?? [];

        // Por defecto, por regla de negocio,
        // siempre agregamos el rol base ROLE_USER a todos los usuarios
        $roles[] = 'ROLE_USER';

        // Retornamos un arreglos solo con valores no nulos y únicos
        return array_values(array_unique($roles));
    }

    // Metodo setRoles() para modificar los roles de un usuario
    public function setRoles(array $roles): void
    {
        // Asignamos como roles el arreglo pero solo con valores no nulos y únicos
        $this->roles = array_values(array_unique($roles));
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