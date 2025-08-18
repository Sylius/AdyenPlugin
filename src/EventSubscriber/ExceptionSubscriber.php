<?php

namespace Sylius\AdyenPlugin\EventSubscriber;


use Adyen\AdyenException;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\AdyenPlugin\Exception\UnprocessablePaymentException;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver\NoCommandResolvedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof UnprocessablePaymentException) {
            $event->setResponse(new JsonResponse([
                'error' => 'Unprocessable Payment',
                'details' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        if ($exception instanceof AdyenException) {
            $event->setResponse(new JsonResponse([
                'error' => $exception->getMessage(),
                'details' => [
                    'errorCode' => $exception->getAdyenErrorCode(),
                    'status' => $exception->getStatus(),
                    'errorType' => $exception->getErrorType(),
                    'pspReference' => $exception->getPspReference(),
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        if ($exception instanceof NoCommandResolvedException) {
            $event->setResponse(new JsonResponse([
                'error' => 'No command resolved for notification',
                'details' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        if ($exception instanceof UnmappedAdyenActionException) {
            $event->setResponse(new JsonResponse([
                'error' => 'Unmapped Adyen action',
                'details' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}
