<?php

namespace App\Tests\Security;

use App\Controller\OnboardingController;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Unit tests for OnboardingController.
 *
 * All tests run with pure PHPUnit mocks — no real database, no HTTP kernel,
 * no Symfony container. The anonymous-class technique mirrors RegistrationBlockTest.
 *
 * ─── HOW TO RUN ────────────────────────────────────────────────────────────
 *   vendor\bin\phpunit tests/Security/OnboardingControllerTest.php --colors
 * ───────────────────────────────────────────────────────────────────────────
 */
class OnboardingControllerTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Returns a UserRepository mock whose count() returns $count.
     */
    private function makeUserRepo(int $count): UserRepository
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('count')->willReturn($count);
        return $repo;
    }

    /**
     * Builds the anonymous-class controller used for index() tests.
     * Overrides getUser(), redirectToRoute() and render() so nothing
     * touches the Symfony DI container.
     */
    private function makeIndexController(?UserInterface $loggedInUser = null): OnboardingController
    {
        return new class($loggedInUser) extends OnboardingController {
            public ?string $redirectRoute = null;
            public ?string $renderedView  = null;

            public function __construct(private readonly ?UserInterface $fakeUser) {}

            public function getUser(): ?UserInterface
            {
                return $this->fakeUser;
            }

            protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
            {
                $this->redirectRoute = $route;
                return new RedirectResponse('/' . $route, $status);
            }

            protected function render(string $view, array $parameters = [], ?Response $response = null): Response
            {
                $this->renderedView = $view;
                return new Response($view);
            }
        };
    }

    /**
     * Builds the anonymous-class controller used for submit() tests.
     * Overrides getUser() and json() so we can inspect JSON responses.
     */
    private function makeSubmitController(?UserInterface $loggedInUser = null): OnboardingController
    {
        return new class($loggedInUser) extends OnboardingController {
            public function __construct(private readonly ?UserInterface $fakeUser) {}

            public function getUser(): ?UserInterface
            {
                return $this->fakeUser;
            }

            protected function json(
                mixed $data,
                int $status = 200,
                array $headers = [],
                array $context = []
            ): JsonResponse {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }

    /** Shared dependency mocks wired up for submit() calls. */
    private function makeSubmitDeps(): array
    {
        return [
            $this->createMock(SettingRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(Security::class),
            $this->createMock(KernelInterface::class),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // index() tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * When a user is already logged in, GET /onboarding must redirect to
     * the dashboard — regardless of how many users are in the database.
     */
    public function testIndexRedirectsToDashboardWhenLoggedIn(): void
    {
        $fakeUser = $this->createMock(UserInterface::class);
        $ctrl     = $this->makeIndexController($fakeUser);
        $userRepo = $this->makeUserRepo(0); // count doesn't matter here

        $response = $ctrl->index($userRepo);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('app_dashboard', $ctrl->redirectRoute);
    }

    /**
     * When no user is logged in but the DB already has users, GET /onboarding
     * must redirect to the login page.
     */
    public function testIndexRedirectsToLoginWhenUsersExistInDb(): void
    {
        $ctrl     = $this->makeIndexController(null); // no logged-in user
        $userRepo = $this->makeUserRepo(3);           // 3 existing users

        $response = $ctrl->index($userRepo);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('app_login', $ctrl->redirectRoute);
    }

    /**
     * When no user is logged in and the DB is empty, GET /onboarding must
     * render the onboarding Twig template.
     */
    public function testIndexRendersOnboardingPageWhenDbIsEmpty(): void
    {
        $ctrl     = $this->makeIndexController(null); // no logged-in user
        $userRepo = $this->makeUserRepo(0);            // empty database

        $response = $ctrl->index($userRepo);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('onboarding/index.html.twig', $ctrl->renderedView);
    }

    /**
     * When ONBOARDING_BYPASS=true is set, the controller must skip all guards
     * and render the onboarding page — even when a user is logged in and DB has users.
     */
    public function testIndexRendersWhenBypassFlagIsEnabled(): void
    {
        $_ENV['ONBOARDING_BYPASS'] = 'true';

        try {
            $fakeUser = $this->createMock(UserInterface::class); // logged-in user
            $ctrl     = $this->makeIndexController($fakeUser);
            $userRepo = $this->makeUserRepo(5); // DB has users

            $response = $ctrl->index($userRepo);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertSame('onboarding/index.html.twig', $ctrl->renderedView);
        } finally {
            // Always restore the env so other tests are not affected.
            $_ENV['ONBOARDING_BYPASS'] = 'false';
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // submit() tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * When a user is already logged in, POST /onboarding/submit must
     * return 403 Forbidden.
     */
    public function testSubmitReturnsForbiddenWhenUserIsLoggedIn(): void
    {
        $fakeUser = $this->createMock(UserInterface::class);
        $ctrl     = $this->makeSubmitController($fakeUser);
        $userRepo = $this->makeUserRepo(0);

        [$settingRepo, $em, $hasher, $security, $kernel] = $this->makeSubmitDeps();

        $response = $ctrl->submit(new Request(), $userRepo, $settingRepo, $em, $hasher, $security, $kernel);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Access Denied', $body['error']);
    }

    /**
     * When admin fields are missing, submit() must return 400 Bad Request.
     */
    public function testSubmitReturnsBadRequestWhenAdminFieldsMissing(): void
    {
        $ctrl     = $this->makeSubmitController(null); // not logged in
        $userRepo = $this->makeUserRepo(0);             // no users in DB

        [$settingRepo, $em, $hasher, $security, $kernel] = $this->makeSubmitDeps();

        // Send a completely empty POST body — all required fields are missing.
        $response = $ctrl->submit(new Request(), $userRepo, $settingRepo, $em, $hasher, $security, $kernel);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('required', $body['error']);
    }

    /**
     * When passwords don't match, submit() must return 400 Bad Request.
     */
    public function testSubmitReturnsBadRequestWhenPasswordsMismatch(): void
    {
        $ctrl     = $this->makeSubmitController(null);
        $userRepo = $this->makeUserRepo(0);

        [$settingRepo, $em, $hasher, $security, $kernel] = $this->makeSubmitDeps();

        $request = new Request([], [
            'admin_username'         => 'adminuser',
            'admin_firstname'        => 'John',
            'admin_lastname'         => 'Doe',
            'admin_email'            => 'john@example.com',
            'admin_password'         => 'secret123',
            'admin_password_confirm' => 'DIFFERENT999', // intentionally wrong
        ]);

        $response = $ctrl->submit($request, $userRepo, $settingRepo, $em, $hasher, $security, $kernel);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Passwords do not match', $body['error']);
    }
}
