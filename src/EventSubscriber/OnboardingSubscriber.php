<?php

namespace App\EventSubscriber;

use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

class OnboardingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private RouterInterface $router
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Run at priority 5 to execute before controllers
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Whitelist the onboarding routes to prevent redirect loop
        if ($route === 'app_onboarding' || $route === 'app_onboarding_submit') {
            return;
        }

        // Whitelist debug, profiler, and assets
        if ($route && (str_starts_with($route, '_wdt') || str_starts_with($route, '_profiler'))) {
            return;
        }

        $path = $request->getPathInfo();
        if (str_contains($path, '/build/') || str_contains($path, '/uploads/') || str_contains($path, '/assets/')) {
            return;
        }

        try {
            $userCount = $this->userRepository->count([]);
            if ($userCount === 0) {
                $url = $this->router->generate('app_onboarding');
                $event->setResponse(new RedirectResponse($url));
            }
        } catch (\Exception $e) {
            // DB not loaded or table not created, ignore and bypass
        }
    }
}
