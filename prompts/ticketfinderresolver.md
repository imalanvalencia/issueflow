# Implementar un ValueResolver personalizado para resolver incidencias en Symfony

Quiero refactorizar el controlador `ApiTicketController` de este proyecto Symfony para eliminar la duplicación de código existente en los métodos `show()`, `update()` y `delete()`.

## 1. Contexto del proyecto

Antes de realizar cambios:

- Lee `AGENTS.md` y respeta sus instrucciones.
- Revisa `composer.json` para confirmar las versiones de PHP y Symfony.
- Analiza `ApiTicketController`, `TicketCatalogInterface`, `Ticket` y la implementación actual del catálogo.
- Revisa `config/services.yaml`.
- Examina las pruebas existentes en `tests/Controller/ApiTicketControllerTest.php`.

El proyecto utiliza:
- PHP 8.4.
- Symfony 8.1.
- Doctrine ORM 3.7.
- Autowiring y autoconfiguración de servicios.
- Atributos PHP para configurar rutas y metadatos del framework.

No introduzcas dependencias externas ni modifiques funcionalidades ajenas a este refactor.

## 2. Problema que hay que resolver

Actualmente, los siguientes tres métodos de `ApiTicketController` repiten la misma lógica:

- `show(string $code)`
- `update(string $code, TicketUpdatePayload $payload)`
- `delete(string $code)`

En los tres casos se realizan estas operaciones:

1. Comprobar que el código cumple el formato `INC-0000`, utilizando la expresión regular `/^INC-\d{4}$/`.
2. Buscar la incidencia mediante `TicketCatalogInterface::findByCode($code)`.
3. Si el formato es incorrecto, devolver HTTP 400 con este JSON:

```json
{
  "error": {
    "code": "invalid_ticket_code",
    "message": "El código debe tener el formato INC-0000"
  }
}
```

4. Si la incidencia no existe, devolver HTTP 404 con este JSON:

```json
{
  "error": {
    "code": "ticket_not_found",
    "message": "La incidencia solicitada no existe"
  }
}
```

Quiero eliminar completamente esta duplicación.

## 3. Solución arquitectónica requerida

Implementa un ValueResolver personalizado de Symfony.

La solución debe tener las siguientes piezas:

### A. TicketFinder

Crea un servicio `TicketFinder` en `src/Service/`.

Responsabilidades:

- Recibir `TicketCatalogInterface` mediante inyección de dependencias.
- Implementar un método público `findOrFail(string $code): Ticket`.
- Validar el formato del código con `/^INC-\d{4}$/`.
- Buscar la incidencia mediante `TicketCatalogInterface::findByCode()`.
- Lanzar una excepción específica si el formato es incorrecto.
- Lanzar otra excepción específica si la incidencia no existe.

No debe depender de objetos HTTP ni de controladores.

### B. Excepciones específicas

Crea dos excepciones en `src/Exception/`:

- `InvalidTicketCodeException`
- `TicketNotFoundException`

Deben permitir identificar inequívocamente cada situación.

No deben contener dependencias del framework HTTP.

### C. TicketValueResolver

Crea `TicketValueResolver` en `src/ArgumentResolver/`.

Debe implementar:

`Symfony\Component\HttpKernel\Controller\ValueResolverInterface`

Su responsabilidad será:

1. Comprobar si el argumento del controlador que está resolviendo es de tipo `App\Entity\Ticket`.
2. Si no es de ese tipo, devolver un iterable vacío, sin interferir con otros resolvers.
3. Obtener el atributo `code` de la petición.
4. Delegar la validación y búsqueda en `TicketFinder`.
5. Entregar el objeto `Ticket` mediante `yield`.

El resolver no debe implementar directamente la expresión regular ni acceder al catálogo.

Utiliza constructor property promotion y tipado estricto.

### D. Gestión centralizada de errores HTTP

Crea un `EventSubscriber` en `src/EventSubscriber/` que transforme las excepciones anteriores en respuestas JSON.

Debe escuchar `KernelEvents::EXCEPTION`.

Las respuestas deben conservar exactamente el contrato HTTP existente:

- `InvalidTicketCodeException`: HTTP 400 y código `invalid_ticket_code`.
- `TicketNotFoundException`: HTTP 404 y código `ticket_not_found`.

Los mensajes deben mantenerse exactamente como están definidos actualmente en el controlador.

Para cualquier otra excepción, el subscriber no debe intervenir ni modificar el comportamiento normal de Symfony.

### E. Registro de servicios

Aprovecha la configuración actual de `config/services.yaml`.

El proyecto ya utiliza:

```yaml
_defaults:
    autowire: true
    autoconfigure: true

App\:
    resource: '../src/'
```

Por tanto, utiliza autoconfiguración siempre que sea posible.

Registra el ValueResolver con el tag `controller.argument_value_resolver` si es necesario, y configura una prioridad adecuada para evitar conflictos con otros resolvers.

No introduzcas configuraciones redundantes ni registres manualmente servicios que ya se autoconfiguran.

## 4. Modificar ApiTicketController

Modifica exclusivamente los tres métodos afectados.

Quiero que sus firmas reciban directamente un objeto `Ticket` en lugar del código `string $code`.

El resultado esperado es conceptualmente el siguiente:

```php
public function show(Ticket $ticket): JsonResponse
```

```php
public function update(
    Ticket $ticket,
    #[MapRequestPayload(
        acceptFormat: 'json',
        validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
    )]
    TicketUpdatePayload $payload,
): JsonResponse
```

```php
public function delete(Ticket $ticket): Response
```

Mantén intacta la lógica funcional de actualización, eliminación y serialización de las respuestas.

En particular:

- No modifiques el comportamiento de `TicketUpdatePayload`.
- No alteres las respuestas JSON satisfactorias.
- No cambies el código HTTP 204 de eliminación.
- No modifiques los métodos `index()` y `create()`.
- No cambies las rutas existentes.
- Elimina las comprobaciones de formato y las búsquedas repetidas de los tres métodos.

El controlador no debe depender directamente de `TicketCatalogInterface` si, después del refactor, deja de utilizarlo.

Mantén el método privado `ticketToArray()`.

## 5. Pruebas automatizadas

Amplía las pruebas existentes en `tests/Controller/ApiTicketControllerTest.php`.

Comprueba mediante peticiones HTTP reales que el ValueResolver funciona correctamente.

Como mínimo, deben existir pruebas para:

### GET

- Obtener una incidencia existente: HTTP 200.
- Código con formato incorrecto: HTTP 400.
- Código válido pero inexistente: HTTP 404.

### PATCH

- Actualizar correctamente una incidencia: HTTP 200.
- Código con formato incorrecto: HTTP 400.
- Código válido pero inexistente: HTTP 404.
- Mantener la validación del payload y sus respuestas HTTP 422.

### DELETE

- Eliminar correctamente una incidencia: HTTP 204.
- Código con formato incorrecto: HTTP 400.
- Código válido pero inexistente: HTTP 404.

Verifica que los cuerpos JSON de error mantienen exactamente la estructura y los mensajes anteriores.

No sustituyas las pruebas funcionales por pruebas unitarias del resolver: necesitamos comprobar que Symfony ejecuta correctamente la resolución de argumentos durante una petición HTTP.

## 6. Criterios de calidad

- Sigue las convenciones de Symfony 8.1 y PHP 8.4.
- Utiliza `declare(strict_types=1)`.
- Utiliza clases `final` cuando corresponda.
- Utiliza inyección de dependencias por constructor.
- Mantén `TicketCatalogInterface` como contrato de acceso al catálogo.
- Evita duplicar reglas de validación.
- No introduzcas lógica de negocio en el ValueResolver.
- No devuelvas respuestas HTTP directamente desde el ValueResolver.
- No utilices excepciones genéricas para representar errores de negocio.
- No modifiques innecesariamente otros archivos.

## 7. Validación final

Una vez implementado:

1. Ejecuta la suite de pruebas con `php bin/phpunit`.
2. Comprueba que el contenedor de Symfony compila correctamente.
3. Comprueba que el ValueResolver está registrado.
4. Corrige cualquier error introducido por los cambios.
5. Revisa que los tres métodos del controlador ya no contienen lógica duplicada de validación ni búsqueda.

## 8. Resultado esperado

Al finalizar, presenta:

- Archivos creados.
- Archivos modificados.
- Explicación breve de la responsabilidad de cada nueva clase.
- Código final de las firmas de los tres métodos del controlador.
- Resultado de las pruebas ejecutadas.
- Cualquier consideración importante sobre el funcionamiento del ValueResolver.

Importante: implementa los cambios directamente en el proyecto. No te limites a proporcionar ejemplos o instrucciones de código.