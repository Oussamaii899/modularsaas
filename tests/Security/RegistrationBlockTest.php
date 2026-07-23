<?php

namespace App\Tests\Security;

use App\Controller\RegistrationController;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class RegistrationBlockTest extends TestCase
{
    public function testRegistrationBlockedWhenUserExists(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('count')->willReturn(1);

        $controller = new class extends RegistrationController {
            public ?string $redirectRoute = null;
            public array $flashes = [];

            protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
            {
                $this->redirectRoute = $route;
                return new RedirectResponse('/login', $status);
            }

            protected function addFlash(string $type, mixed $message): void
            {
                $this->flashes[$type][] = $message;
            }
        };

        $request = new Request();
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $security = $this->createMock(Security::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $controller->register($request, $hasher, $security, $em, $userRepository);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('app_login', $controller->redirectRoute);
        $this->assertNotEmpty($controller->flashes['error']);
    }

    public function testRegistrationAllowedWhenNoUsersExist(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('count')->willReturn(0);

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('isSubmitted')->willReturn(false);

        $controller = new class($formMock) extends RegistrationController {
            public function __construct(private FormInterface $mockForm) {}

            protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
            {
                return $this->mockForm;
            }

            protected function render(string $view, array $parameters = [], ?Response $response = null): Response
            {
                return new Response('register_page');
            }
        };

        $request = new Request();
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $security = $this->createMock(Security::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $controller->register($request, $hasher, $security, $em, $userRepository);

        $this->assertEquals('register_page', $response->getContent());
    }
}
