<?php

namespace App\EventSubscriber;

use App\Repository\SettingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class MaintenanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SettingRepository $settingRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Environment $twig
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Run at priority 0 to ensure the security context (firewall) is already loaded
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Whitelist routes so administrators can log in/out and settings page is always accessible
        $allowedRoutes = [
            'app_login', 
            'app_logout', 
            'app_settings', 
            'app_settings_toggle_maintenance',
            'app_settings_backup_restore', 
            'app_settings_backup_create', 
            'app_settings_clear_cache', 
            'app_settings_recalculate', 
            'app_settings_export_data', 
            'app_onboarding',
            'app_onboarding_submit',
            '_wdt', 
            '_profiler'
        ];

        if (in_array($route, $allowedRoutes, true) || str_starts_with($request->getPathInfo(), '/_profiler')) {
            return;
        }

        try {
            $maintenanceSetting = $this->settingRepository->findOneBy(['keyName' => 'maintenance_enabled']);
            $isEnabled = $maintenanceSetting ? $maintenanceSetting->getValue() === '1' : false;

            if ($isEnabled) {
                $isAdmin = false;
                try {
                    $isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');
                } catch (\Exception $e) {
                    // Security context not loaded or anonymous
                }

                if (!$isAdmin) {
                    $html = $this->twig->render('error/maintenance.html.twig');
                    $event->setResponse(new Response($html, 503));
                }
            }
        } catch (\Exception $e) {
            // DB not loaded or error, bypass
        }
    }
}
