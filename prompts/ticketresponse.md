# Refactorizar ApiTicketController utilizando DTO + Mapper

Quiero refactorizar el proyecto IssueFlow para eliminar del controlador `ApiTicketController` la responsabilidad de transformar las entidades `Ticket` en arrays destinados a las respuestas JSON.

La solución debe seguir el patrón **DTO (Data Transfer Object) + Mapper**, respetando la arquitectura actual del proyecto y las convenciones de Symfony.

Implementa los cambios directamente en el proyecto.

## 1. Análisis previo

Antes de modificar código:

1. Lee `AGENTS.md` y respeta sus instrucciones.
2. Revisa `composer.json` y confirma las versiones de PHP y Symfony.
3. Analiza la entidad `Ticket`.
4. Analiza `ApiTicketController`.
5. Revisa los DTOs existentes:
   - `TicketCreatePayload`
   - `TicketUpdatePayload`
   - `TicketListQuery`
6. Revisa la implementación actual de `TicketValueResolver`, `TicketFinder` y `ApiExceptionSubscriber`.
7. Analiza `TicketPaginator` y las pruebas existentes de `ApiTicketController`.

No modifiques funcionalidades que no estén relacionadas con este refactor.

## 2. Situación actual

Actualmente, `ApiTicketController` contiene el siguiente método privado:

```php
private function ticketToArray(Ticket $ticket): array
{
    return [
        'code' => $ticket->getCode(),
        'title' => $ticket->getTitle(),
        'priority' => $ticket->getPriority()->value,
        'status' => $ticket->getStatus()->value,
    ];
}
```

Este método es responsable de transformar una entidad `Ticket` en la representación que devuelve la API.

Se utiliza en cuatro endpoints:

- `index()`
- `create()`
- `show()`
- `update()`

Quiero eliminar este método del controlador y trasladar su responsabilidad a una clase independiente.

## 3. Crear el DTO TicketResponse

Crea la clase:

`src/Api/Model/TicketResponse.php`

Debe ser un DTO inmutable (`final readonly`) que represente la respuesta pública de una incidencia.

Debe contener exactamente estas propiedades:

```php
public string $code;
public string $title;
public string $priority;
public string $status;
```

Utiliza un constructor con property promotion.

Requisitos:

- No debe depender de la entidad `Ticket`.
- No debe depender de Doctrine.
- No debe depender de componentes HTTP de Symfony.
- No debe contener lógica de negocio.
- Debe utilizar `declare(strict_types=1)`.

Su responsabilidad es exclusivamente definir la estructura de datos que expondrá la API.

## 4. Crear TicketResponseMapper

Crea la clase:

`src/Api/Mapper/TicketResponseMapper.php`

Debe ser una clase `final` responsable de transformar entidades `Ticket` en objetos `TicketResponse`.

Implementa los siguientes métodos:

### map()

```php
public function map(Ticket $ticket): TicketResponse
```

Debe realizar exactamente el mismo mapeo que actualmente realiza `ticketToArray()`:

- `code`: `$ticket->getCode()`
- `title`: `$ticket->getTitle()`
- `priority`: `$ticket->getPriority()->value`
- `status`: `$ticket->getStatus()->value`

### mapCollection()

Implementa también:

```php
public function mapCollection(array $tickets): array
```

Debe transformar una colección de entidades `Ticket` en una colección de objetos `TicketResponse`.

Utiliza `array_map()` y reutiliza internamente el método `map()`.

Documenta los tipos de entrada y salida mediante PHPDoc:

```php
/**
 * @param Ticket[] $tickets
 * @return TicketResponse[]
 */
```

No dupliques la lógica de transformación entre ambos métodos.

El mapper no debe depender del controlador, del Serializer de Symfony ni de Doctrine.

## 5. Integración en ApiTicketController

Inyecta `TicketResponseMapper` mediante el constructor utilizando autowiring.

Utiliza inyección de dependencias por constructor y `readonly` cuando corresponda.

Elimina completamente el método privado `ticketToArray()`.

Adapta los cuatro endpoints:

### index()

En lugar de transformar manualmente cada entidad, utiliza:

```php
$items = $this->ticketResponseMapper->mapCollection(
    $ticketPage->getItems()
);
```

Mantén intactos:

- La paginación.
- Los metadatos de paginación.
- Los filtros.
- El ordenamiento.
- La estructura de la respuesta JSON.

### create()

Utiliza:

```php
$this->ticketResponseMapper->map($ticket)
```

Mantén intactos:

- El código HTTP 201.
- La cabecera `Location`.
- La estructura JSON de la respuesta.
- El comportamiento actual de creación.

### show()

El método debe recibir directamente `Ticket $ticket`, aprovechando el `TicketValueResolver` implementado anteriormente.

Utiliza el mapper para generar la respuesta.

La firma debe conservar el comportamiento del endpoint:

```php
public function show(Ticket $ticket): JsonResponse
```

### update()

Mantén el uso de `TicketUpdatePayload` y la lógica de actualización existente.

Después de persistir los cambios, utiliza el mapper para construir la respuesta.

No alteres el comportamiento de actualización ni la validación del payload.

### delete()

No necesita modificaciones porque no devuelve una entidad.

## 6. Mantener la arquitectura existente

Respeta las siguientes decisiones arquitectónicas:

- `TicketCatalogInterface` continúa siendo el contrato de acceso al catálogo.
- `TicketFinder` mantiene la responsabilidad de validar y localizar incidencias.
- `TicketValueResolver` mantiene la responsabilidad de resolver el argumento `Ticket`.
- `ApiExceptionSubscriber` mantiene la conversión de excepciones en respuestas HTTP.
- `TicketResponseMapper` se responsabiliza exclusivamente de transformar entidades en DTOs.
- `TicketResponse` define el contrato de salida de la API.
- `ApiTicketController` coordina las operaciones HTTP.

No introduzcas el componente Symfony Serializer para esta solución.

No añadas atributos `Groups` a la entidad `Ticket`.

No modifiques la entidad ni los repositorios Doctrine.

No introduzcas interfaces adicionales para el mapper si no existe una necesidad arquitectónica real.

Aprovecha el autowiring y la autoconfiguración que ya existen en `config/services.yaml`.

## 7. Compatibilidad de las respuestas JSON

Este punto es especialmente importante.

El refactor debe ser transparente para los consumidores de la API.

Las respuestas deben conservar exactamente la estructura JSON existente.

Por ejemplo, una respuesta individual debe seguir teniendo esta forma:

```json
{
  "ticket": {
    "code": "INC-1001",
    "title": "Error de autenticación",
    "priority": "high",
    "status": "open"
  }
}
```

La colección debe mantener la estructura actual:

```json
{
  "tickets": [
    {
      "code": "INC-1001",
      "title": "Error de autenticación",
      "priority": "high",
      "status": "open"
    }
  ]
}
```

Importante: Symfony debe serializar correctamente los objetos `TicketResponse` devueltos por el mapper.

Comprueba que `AbstractController::json()` y el normalizador disponible en el proyecto serializan correctamente el DTO.

No sustituyas los objetos DTO por arrays para solucionar problemas de serialización, porque eso eliminaría el objetivo del refactor.

Si es necesario, implementa una solución compatible con la arquitectura actual para que los DTOs se conviertan correctamente en JSON.

## 8. Pruebas automatizadas

Añade pruebas unitarias para:

`tests/Api/Mapper/TicketResponseMapperTest.php`

Comprueba:

1. Que `map()` devuelve una instancia de `TicketResponse`.
2. Que los cuatro campos tienen los valores correctos.
3. Que los enums se convierten a sus valores escalares.
4. Que `mapCollection()` transforma correctamente una colección.
5. Que una colección vacía devuelve un array vacío.

Utiliza los mecanismos de creación de entidades y mocks que ya existan en el proyecto.

No introduzcas dependencias de testing nuevas.

### Pruebas funcionales

Revisa y amplía `ApiTicketControllerTest.php`.

Comprueba que:

- `index()` mantiene la estructura JSON.
- `create()` mantiene la estructura JSON y el HTTP 201.
- `show()` mantiene la estructura JSON y el HTTP 200.
- `update()` mantiene la estructura JSON y el HTTP 200.
- Los valores de `priority` y `status` siguen siendo strings con los valores de los enums.

No alteres las pruebas relacionadas con la resolución de incidencias, validación de códigos o errores HTTP, salvo que sea necesario por el refactor.

## 9. Calidad del código

Aplica estas convenciones:

- PHP 8.4.
- Symfony 8.1.
- `declare(strict_types=1)`.
- Clases `final` cuando corresponda.
- DTOs inmutables mediante `readonly`.
- Tipado estricto en parámetros y retornos.
- PHPDoc para colecciones.
- Inyección de dependencias por constructor.
- Código sencillo y sin abstracciones innecesarias.

Evita introducir complejidad que no aporte valor.

## 10. Validación final

Cuando termines:

1. Ejecuta todas las pruebas con:

```bash
php bin/phpunit
```

2. Comprueba que el contenedor de Symfony compila:

```bash
php bin/console lint:container
```

3. Comprueba que la configuración y los servicios siguen siendo válidos.
4. Ejecuta las herramientas de análisis estático o estilo que estén configuradas en el proyecto, si existen.
5. Corrige los errores introducidos.

## 11. Informe final

Al finalizar, presenta:

- Archivos creados.
- Archivos modificados.
- Explicación de las responsabilidades de `TicketResponse` y `TicketResponseMapper`.
- Cambios realizados en `ApiTicketController`.
- Confirmación de que se ha eliminado `ticketToArray()`.
- Resultado de las pruebas unitarias y funcionales.
- Confirmación de que las respuestas JSON mantienen el contrato anterior.

Importante: realiza la implementación directamente en el proyecto. No te limites a proporcionar ejemplos de código ni a describir los cambios que habría que hacer.