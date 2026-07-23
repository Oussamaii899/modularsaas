<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $apiLimiterFactory,
        private RateLimiterFactory $aiReportLimiterFactory
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // 1. Apply API Limiter for routes starting with /api/
        if (str_starts_with($path, '/api/')) {
            $limiter = $this->apiLimiterFactory->create($request->getClientIp());
            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                $event->setResponse(new JsonResponse(
                    ['error' => 'Too many requests. Please try again later.'],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()]
                ));
            }
            return;
        }

        // 2. Apply AI Report Limiter for growth report paths
        if (str_starts_with($path, '/sales/overview/growth-report')) {
            $limiter = $this->aiReportLimiterFactory->create($request->getClientIp());
            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                $isJson = $request->isXmlHttpRequest()
                    || str_contains($request->headers->get('Accept', ''), 'application/json');

                if ($isJson) {
                    $response = new JsonResponse(
                        ['error' => 'Rate limit exceeded. You can only request CFO growth reports up to 3 times per hour.'],
                        Response::HTTP_TOO_MANY_REQUESTS,
                        ['Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()]
                    );
                } else {
                    $response = new Response(
                        '<h1>429 Too Many Requests</h1><p>Rate limit exceeded. You can only request CFO growth reports up to 3 times per hour.</p>',
                        Response::HTTP_TOO_MANY_REQUESTS,
                        ['Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()]
                    );
                }
                $event->setResponse($response);
            }
        }
    }
}
