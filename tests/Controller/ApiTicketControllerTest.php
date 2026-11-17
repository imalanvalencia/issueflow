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
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_ticket_code', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code']);

        $this->client->request(Request::METHOD_PATCH, '/api/tickets/INC-9999', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('ticket_not_found', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code']);
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
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_ticket_code', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code']);

        $this->client->request(Request::METHOD_DELETE, '/api/tickets/INC-9999');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('ticket_not_found', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code']);
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
}
