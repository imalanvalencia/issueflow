<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\InvalidTicketCodeException;
use App\Exception\TicketNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiTicketExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        [$statusCode, $errorCode] = match (true) {
            $exception instanceof InvalidTicketCodeException => [
                Response::HTTP_BAD_REQUEST,
                'invalid_ticket_code',
            ],
            $exception instanceof TicketNotFoundException => [
                Response::HTTP_NOT_FOUND,
                'ticket_not_found',
            ],
            default => [null, null],
        };

        if ($statusCode === null) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => $errorCode,
                'message' => $exception->getMessage(),
            ],
        ], $statusCode));
    }
}
