<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Setting;
use App\Repository\UserRepository;
use App\Repository\SettingRepository;
use App\Security\Authenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\KernelInterface;

class OnboardingController extends AbstractController
{
    #[Route('/onboarding', name: 'app_onboarding', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        // Set ONBOARDING_BYPASS=true in .env.local to skip guards during manual testing.
        $bypass = ($_ENV['ONBOARDING_BYPASS'] ?? 'false') === 'true';

        // Onboarding is only accessible if there is NO logged-in user and NO user in the database.
        if (!$bypass && $this->getUser() !== null) {
            return $this->redirectToRoute('app_dashboard');
        }

        if (!$bypass && $userRepository->count([]) > 0) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('onboarding/index.html.twig');
    }

    #[Route('/onboarding/submit', name: 'app_onboarding_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        UserRepository $userRepository,
        SettingRepository $settingRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        KernelInterface $kernel
    ): Response {
        // Set ONBOARDING_BYPASS=true in .env.local to skip guards during manual testing.
        $bypass = ($_ENV['ONBOARDING_BYPASS'] ?? 'false') === 'true';

        // Access control: onboarding submission only allowed if NO logged-in user and NO user in DB.
        if (!$bypass && ($this->getUser() !== null || $userRepository->count([]) > 0)) {
            return $this->json(['success' => false, 'error' => 'Access Denied.'], Response::HTTP_FORBIDDEN);
        }

        // Retrieve post parameters
        $username = trim($request->request->get('admin_username', ''));
        $firstname = trim($request->request->get('admin_firstname', ''));
        $lastname = trim($request->request->get('admin_lastname', ''));
        $email = trim($request->request->get('admin_email', ''));
        $plainPassword = $request->request->get('admin_password', '');
        $passwordConfirm = $request->request->get('admin_password_confirm', '');

        // Validation for administrator details (only if creating first admin, or if fields are provided in re-onboarding)
        // BUG FIX: $userCount was undefined; now reads directly from the repository.
        $userCount = $userRepository->count([]);
        if ($userCount === 0 || $username !== '') {
            if ($username === '' || $firstname === '' || $lastname === '' || $email === '' || $plainPassword === '') {
                return $this->json(['success' => false, 'error' => 'All administrator details are required.'], Response::HTTP_BAD_REQUEST);
            }

            if ($plainPassword !== $passwordConfirm) {
                return $this->json(['success' => false, 'error' => 'Passwords do not match.'], Response::HTTP_BAD_REQUEST);
            }

            if (strlen($plainPassword) < 6) {
                return $this->json(['success' => false, 'error' => 'Password must be at least 6 characters long.'], Response::HTTP_BAD_REQUEST);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['success' => false, 'error' => 'Invalid email address format.'], Response::HTTP_BAD_REQUEST);
            }

            // Check for duplicate username or email
            $existingUser = $userRepository->findOneBy(['username' => $username]);
            if ($existingUser) {
                return $this->json(['success' => false, 'error' => 'Username is already taken.'], Response::HTTP_BAD_REQUEST);
            }

            $existingEmail = $userRepository->findOneBy(['email' => $email]);
            if ($existingEmail) {
                return $this->json(['success' => false, 'error' => 'Email address is already registered.'], Response::HTTP_BAD_REQUEST);
            }

            // Create admin user
            $user = new User();
            $user->setUsername($username);
            $user->setFirstname($firstname);
            $user->setLastname($lastname);
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_ADMIN']);
            $entityManager->persist($user);
        } else {
            // Re-onboarding of existing admin, use currently logged-in user
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['success' => false, 'error' => 'User not found.'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Save General Settings
        $settingsData = [
            'business_name' => trim($request->request->get('business_name', '')),
            'company_name' => trim($request->request->get('business_name', '')), // Default company name to business name
            'email' => trim($request->request->get('business_email', '')),
            'phone' => trim($request->request->get('business_phone', '')),
            'address' => trim($request->request->get('business_address', '')),
            'website' => trim($request->request->get('business_website', '')),
            'currency' => trim($request->request->get('currency', '$')),
            'timezone' => trim($request->request->get('timezone', 'UTC')),
            'theme' => trim($request->request->get('theme', 'light')),
            'primary_color' => trim($request->request->get('primary_color', 'indigo')),
            'sidebar_style' => trim($request->request->get('sidebar_style', 'light')),
            'invoice_prefix' => 'INV-',
            'invoice_footer' => '',
            'maintenance_enabled' => '0',
        ];

        foreach ($settingsData as $key => $value) {
            $setting = $settingRepository->findOneBy(['keyName' => $key]);
            if (!$setting) {
                $setting = new Setting();
                $setting->setKeyName($key);
                $entityManager->persist($setting);
            }
            $setting->setValue($value);
        }

        // Handle file uploads (business_logo and company_logo)
        $uploadsDir = $kernel->getProjectDir() . '/public/uploads/branding';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }

        foreach (['business_logo', 'company_logo'] as $logoField) {
            $file = $request->files->get($logoField);
            if ($file) {
                $newFilename = $logoField . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $oldSetting = $settingRepository->findOneBy(['keyName' => $logoField]);
                    if ($oldSetting && $oldSetting->getValue()) {
                        $oldFilePath = $uploadsDir . '/' . $oldSetting->getValue();
                        if (file_exists($oldFilePath)) {
                            @unlink($oldFilePath);
                        }
                    }
                    $file->move($uploadsDir, $newFilename);
                    if (!$oldSetting) {
                        $oldSetting = new Setting();
                        $oldSetting->setKeyName($logoField);
                        $entityManager->persist($oldSetting);
                    }
                    $oldSetting->setValue($newFilename);
                } catch (FileException $e) {
                    return $this->json(['success' => false, 'error' => 'Failed to upload ' . str_replace('_', ' ', $logoField) . '.'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
        }

        // Save everything
        $entityManager->flush();

        // Automatically log the admin in
        try {
            if ($user instanceof User) {
                $security->login($user, Authenticator::class, 'main');
            }
        } catch (\Throwable $e) {
            // Silence login exception in CLI or if APP_SECRET is empty
        }

        return $this->json(['success' => true]);
    }
}
