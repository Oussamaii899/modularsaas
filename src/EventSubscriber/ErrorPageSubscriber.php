<?php

namespace App\EventSubscriber;

use App\Repository\SettingRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

final class ErrorPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Security $security,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = $this->getErrorStatusCode($exception);

        if ($statusCode === null) {
            return;
        }

        if ($statusCode === Response::HTTP_FORBIDDEN && $this->security->getUser() === null) {
            return;
        }

        $template = $statusCode === Response::HTTP_NOT_FOUND ? 'error/404.html.twig' : 'error/403.html.twig';
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        $event->setResponse(new Response(
            $this->twig->render($template, [
                'status_code' => $statusCode,
                'status_text' => Response::$statusTexts[$statusCode] ?? '',
                'exception' => $exception,
                'company_logo' => $this->settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
                'company_name' => $this->settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            ]),
            $statusCode,
            $headers,
        ));
    }

    private function getErrorStatusCode(\Throwable $exception): ?int
    {
        if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($exception instanceof NotFoundHttpException) {
            return Response::HTTP_NOT_FOUND;
        }

        if ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
        ], true)) {
            return $exception->getStatusCode();
        }

        return null;
    }
}
