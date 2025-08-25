<?php

namespace Sylius\AdyenPlugin\EventSubscriber;


use Adyen\AdyenException;
use Psr\Log\LoggerInterface;
use Sylius\AdyenPlugin\Exception\NoCommandResolvedException;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\AdyenPlugin\Exception\UnprocessablePaymentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

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

        if ($exception instanceof HttpException) {
            $event->setResponse(new JsonResponse([
                'error' => $exception->getMessage(),
                'details' => [
                    'status' => $exception->getStatusCode(),
                ]
            ], $exception->getStatusCode()));
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

            $this->logger->error(
                'No command resolved for notification: {message}',
                ['message' => $exception->getMessage()]
            );
        }

        if ($exception instanceof UnmappedAdyenActionException) {
            $event->setResponse(new JsonResponse([
                'error' => 'Unmapped Adyen action',
                'details' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        if ($exception instanceof AdyenException) {
            $event->setResponse(new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ], $exception->getCode()));

            $this->logger->error(
                'Adyen exception occurred: {message}, code: {code}, errorCode: {errorCode}, status: {status}, errorType: {errorType}, pspReference: {pspReference}',
                [
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'errorCode' => $exception->getAdyenErrorCode(),
                    'status' => $exception->getStatus(),
                    'errorType' => $exception->getErrorType(),
                    'pspReference' => $exception->getPspReference(),
                ]
            );
        }
    }
}
