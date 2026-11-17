<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Ticket;
use App\Entity\Category;
use App\Entity\User;
use App\Model\TicketPriority;
use App\Model\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class ApiTicketControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
        $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';

        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->createSchema($metadata);

        $this->entityManager->persist(new Ticket(
            code: 'INC-0001',
            title: 'Título original',
            priority: TicketPriority::Normal,
            status: TicketStatus::Open,
        ));
        $this->entityManager->flush();
    }

    public function test_it_shows_an_existing_ticket(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/tickets/INC-0001');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'ticket' => [
                'code' => 'INC-0001',
                'title' => 'Título original',
                'priority' => 'normal',
                'status' => 'open',
            ],
        ], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function test_it_creates_a_ticket_with_the_existing_json_contract(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/tickets',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"code":"INC-2001","title":"Nueva incidencia","priority":"high","status":"open"}',
        );

        self::assertResponseStatusCodeSame(201);
        self::assertSame('/api/tickets/INC-2001', $this->client->getResponse()->headers->get('Location'));
        self::assertSame([
            'ticket' => [
                'code' => 'INC-2001',
                'title' => 'Nueva incidencia',
                'priority' => 'high',
                'status' => 'open',
            ],
        ], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function test_show_returns_the_expected_errors_for_invalid_and_missing_tickets(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/tickets/wrong');
        $this->assertErrorResponse(400, 'invalid_ticket_code', 'El código debe tener el formato INC-0000');

        $this->client->request(Request::METHOD_GET, '/api/tickets/INC-9999');
        $this->assertErrorResponse(404, 'ticket_not_found', 'La incidencia solicitada no existe');
    }

    public function test_it_updates_the_title_and_persists_the_change(): void
    {
        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-0001', [], [], ['CONTENT_TYPE' => 'application/json'], '{"title":"  Título nuevo  "}');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'ticket' => [
                'code' => 'INC-0001',
                'title' => 'Título nuevo',
                'priority' => 'normal',
                'status' => 'open',
            ],
        ], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->entityManager->clear();
        self::assertSame('Título nuevo', $this->entityManager->getRepository(Ticket::class)->findOneBy(['code' => 'INC-0001'])->getTitle());
    }

    public function test_it_updates_priority_and_status_and_keeps_the_code_immutable(): void
    {
        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-0001', [], [], ['CONTENT_TYPE' => 'application/json'], '{"priority":"high","status":"in_progress"}');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'code' => 'INC-0001',
            'title' => 'Título original',
            'priority' => 'high',
            'status' => 'in_progress',
        ], json_decode((string) $this->client->getResponse()->getContent(), true)['ticket']);

        $this->entityManager->clear();
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['code' => 'INC-0001']);
        self::assertSame('INC-0001', $ticket->getCode());
        self::assertSame(TicketPriority::High, $ticket->getPriority());
        self::assertSame(TicketStatus::InProgress, $ticket->getStatus());
    }

    public function test_it_can_update_priority_and_status_independently(): void
    {
        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-0001', [], [], ['CONTENT_TYPE' => 'application/json'], '{"priority":"urgent"}');
        self::assertResponseIsSuccessful();
        $ticket = json_decode((string) $this->client->getResponse()->getContent(), true)['ticket'];
        self::assertSame('urgent', $ticket['priority']);
        self::assertSame('open', $ticket['status']);

        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-0001', [], [], ['CONTENT_TYPE' => 'application/json'], '{"status":"closed"}');
        self::assertResponseIsSuccessful();
        $ticket = json_decode((string) $this->client->getResponse()->getContent(), true)['ticket'];
        self::assertSame('urgent', $ticket['priority']);
        self::assertSame('closed', $ticket['status']);

        $this->entityManager->clear();
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['code' => 'INC-0001']);
        self::assertSame(TicketPriority::Urgent, $ticket->getPriority());
        self::assertSame(TicketStatus::Closed, $ticket->getStatus());
    }

    public function test_empty_payload_and_null_fields_leave_the_ticket_unchanged(): void
    {
        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-0001', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        self::assertResponseIsSuccessful();
        self::assertSame('Título original', json_decode((string) $this->client->getResponse()->getContent(), true)['ticket']['title']);

        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-0001', [], [], ['CONTENT_TYPE' => 'application/json'], '{"title":null,"priority":null,"status":null}');
        self::assertResponseIsSuccessful();
        self::assertSame('Título original', json_decode((string) $this->client->getResponse()->getContent(), true)['ticket']['title']);
    }

    public function test_invalid_payload_values_return_422(): void
    {
        foreach ([
            '{"title":"   "}',
            '{"title":"'.str_repeat('x', 181).'"}',
            '{"priority":"invalid"}',
            '{"status":"invalid"}',
        ] as $json) {
            $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-0001', [], [], ['CONTENT_TYPE' => 'application/json'], $json);
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function test_invalid_code_and_missing_ticket_return_the_expected_errors(): void
    {
        $this->client->request(Request::METHOD_PATCH, '/api/tickets/wrong', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $this->assertErrorResponse(400, 'invalid_ticket_code', 'El código debe tener el formato INC-0000');

        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-9999', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $this->assertErrorResponse(404, 'ticket_not_found', 'La incidencia solicitada no existe');
    }

    public function test_it_deletes_an_existing_ticket_without_a_response_body(): void
    {
        $this->client->request(Request::METHOD_DELETE, '/api/tickets/INC-0001');

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Ticket::class)->findOneBy(['code' => 'INC-0001']));
    }

    public function test_delete_returns_400_for_an_invalid_code_and_404_for_a_missing_ticket(): void
    {
        $this->client->request(Request::METHOD_DELETE, '/api/tickets/wrong');
        $this->assertErrorResponse(400, 'invalid_ticket_code', 'El código debe tener el formato INC-0000');

        $this->client->request(Request::METHOD_DELETE, '/api/tickets/INC-9999');
        $this->assertErrorResponse(404, 'ticket_not_found', 'La incidencia solicitada no existe');
    }

    public function test_deleting_the_same_ticket_twice_returns_204_then_404(): void
    {
        $this->client->request(Request::METHOD_DELETE, '/api/tickets/INC-0001');
        self::assertResponseStatusCodeSame(204);

        $this->client->request(Request::METHOD_DELETE, '/api/tickets/INC-0001');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('ticket_not_found', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code']);
    }

    public function test_deleting_a_ticket_does_not_delete_its_assignee_or_category(): void
    {
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['code' => 'INC-0001']);
        $assignee = new User(name: 'Agente', email: 'agente@example.test');
        $category = new Category(name: 'Soporte');
        $ticket->assignTo($assignee);
        $ticket->setCategory($category);
        $this->entityManager->persist($assignee);
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $assigneeId = $assignee->getId();
        $categoryId = $category->getId();

        $this->client->request(Request::METHOD_DELETE, '/api/tickets/INC-0001');
        self::assertResponseStatusCodeSame(204);

        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(User::class, $assigneeId));
        self::assertNotNull($this->entityManager->find(Category::class, $categoryId));
    }

    public function test_ticket_list_maps_and_applies_query_filters(): void
    {
        foreach ([
            ['INC-0002', 'Factura pendiente', TicketPriority::High, TicketStatus::Open],
            ['INC-0003', 'Problema de acceso', TicketPriority::Urgent, TicketStatus::InProgress],
            ['INC-0004', 'Factura cerrada', TicketPriority::High, TicketStatus::Closed],
            ['INC-0005', 'Segundo asunto', TicketPriority::Normal, TicketStatus::Open],
            ['INC-0006', 'Otro asunto', TicketPriority::Low, TicketStatus::InProgress],
            ['INC-0007', 'Factura abierta', TicketPriority::High, TicketStatus::Open],
        ] as [$code, $title, $priority, $status]) {
            $this->entityManager->persist(new Ticket($code, $title, $priority, $status));
        }
        $this->entityManager->flush();

        $this->client->request(Request::METHOD_GET, '/api/tickets');
        self::assertResponseIsSuccessful();
        $all = json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'];
        self::assertCount(3, $all);
        self::assertSame(['code', 'title', 'priority', 'status'], array_keys($all[0]));

        $this->client->request(Request::METHOD_GET, '/api/tickets?query=Factura&priority=high&status=open');
        self::assertResponseIsSuccessful();
        $filtered = json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'];
        self::assertSame(['INC-0007', 'INC-0002'], array_column($filtered, 'code'));

        $this->client->request(Request::METHOD_GET, '/api/tickets?query=%20Factura%20&priority=%20high%20&status=%20open%20');
        self::assertResponseIsSuccessful();
        self::assertSame(['INC-0007', 'INC-0002'], array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'], 'code'));

        $this->client->request(Request::METHOD_GET, '/api/tickets?query=');
        self::assertResponseIsSuccessful();
        self::assertCount(3, json_decode((string) $this->client->getResponse()->getContent(), true)['tickets']);

        $this->client->request(Request::METHOD_GET, '/api/tickets?priority=high');
        self::assertResponseIsSuccessful();
        self::assertSame(['INC-0007', 'INC-0004', 'INC-0002'], array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'], 'code'));

        $this->client->request(Request::METHOD_GET, '/api/tickets?status=closed');
        self::assertResponseIsSuccessful();
        self::assertSame(['INC-0004'], array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'], 'code'));
    }

    public function test_ticket_list_preserves_page_fallback_and_bounds(): void
    {
        foreach (range(2, 7) as $number) {
            $this->entityManager->persist(new Ticket(
                sprintf('INC-%04d', $number),
                'Ticket '.$number,
                TicketPriority::Normal,
                TicketStatus::Open,
            ));
        }
        $this->entityManager->flush();

        foreach (['not-a-number', '0'] as $page) {
            $this->client->request(Request::METHOD_GET, '/api/tickets?page='.$page);
            self::assertResponseIsSuccessful();
            self::assertSame(['INC-0007', 'INC-0006', 'INC-0005'], array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'], 'code'));
        }

        $this->client->request(Request::METHOD_GET, '/api/tickets?page=2');
        self::assertResponseIsSuccessful();
        self::assertSame(['INC-0004', 'INC-0003', 'INC-0002'], array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'], 'code'));

        $this->client->request(Request::METHOD_GET, '/api/tickets?page=99');
        self::assertResponseIsSuccessful();
        self::assertSame(['INC-0001'], array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['tickets'], 'code'));
    }

    public function test_invalid_ticket_list_filters_return_bad_request(): void
    {
        foreach (['/api/tickets?priority=invalid', '/api/tickets?status=invalid'] as $uri) {
            $this->client->request(Request::METHOD_GET, $uri);
            self::assertResponseStatusCodeSame(400);
            self::assertStringStartsWith('{', (string) $this->client->getResponse()->getContent());
            self::assertStringContainsString('Validation Failed', (string) $this->client->getResponse()->getContent());
        }
    }

    private function assertErrorResponse(int $statusCode, string $code, string $message): void
    {
        self::assertResponseStatusCodeSame($statusCode);
        self::assertSame([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], json_decode((string) $this->client->getResponse()->getContent(), true));
    }
}
