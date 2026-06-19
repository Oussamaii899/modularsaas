<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PermissionUser;
use App\Entity\Permission;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users')]
class UsersController extends AbstractController
{
    private function denyUnlessAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You do not have permission to manage users.');
        }
    }

    #[Route(name: 'app_users_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository, SettingRepository $settingRepository): Response
    {
        $this->denyUnlessAdmin();

        $q = $request->query->get('q');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $qb = $userRepository->createQueryBuilder('u');
        if ($q) {
            $qb->where('u.username LIKE :q OR u.email LIKE :q OR u.firstname LIKE :q OR u.lastname LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        $totalItems = count($qb->getQuery()->getResult());
        $pagesCount = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $pagesCount);

        $users = $qb->orderBy('u.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('users/index.html.twig', [
            'users' => $users,
            'searchQuery' => $q,
            'pagesCount' => $pagesCount,
            'currentPage' => $page,
            'totalItems' => $totalItems,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Users', 'url' => $this->generateUrl('app_users_index')],
            ],
        ]);
    }

    #[Route('/new', name: 'app_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, SettingRepository $settingRepository): Response
    {
        $this->denyUnlessAdmin();

        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Password
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            // Avatar Upload
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                $newFilename = uniqid() . '.' . $avatarFile->guessExtension();
                $avatarFile->move($uploadsDir, $newFilename);
                $user->setAvatar($newFilename);
            }

            // Roles & Permissions
            $isAdmin = $form->get('isAdmin')->getData();
            if ($isAdmin) {
                $user->setRoles(['ROLE_ADMIN']);
            } else {
                $user->setRoles(['ROLE_USER']);
                
                // Save Permissions
                $selectedPermissions = $form->get('permissions')->getData();
                foreach ($selectedPermissions as $permission) {
                    $permUser = new PermissionUser();
                    $permUser->setUser($user);
                    $permUser->setPermission($permission);
                    $entityManager->persist($permUser);
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', sprintf('User %s has been successfully created.', $user->getUsername()));
            return $this->redirectToRoute('app_users_index');
        }

        return $this->render('users/new.html.twig', [
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Users', 'url' => $this->generateUrl('app_users_index')],
                ['label' => 'Add User', 'url' => $this->generateUrl('app_users_new')],
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_users_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, SettingRepository $settingRepository, UserRepository $userRepository): Response
    {
        $this->denyUnlessAdmin();
        $currentUser = $this->getUser();
        $isEditingOwnAdminAccount = $currentUser instanceof User
            && $currentUser->getId() === $user->getId()
            && in_array('ROLE_ADMIN', $user->getRoles(), true);

        // Prepare existing permissions to populate the form
        $selectedPermissions = [];
        foreach ($user->getPermissionUsers() as $permUser) {
            $selectedPermissions[] = $permUser->getPermission();
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
        ]);

        // Pre-populate unmapped fields
        $form->get('isAdmin')->setData(in_array('ROLE_ADMIN', $user->getRoles(), true));
        $form->get('permissions')->setData($selectedPermissions);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isAdmin = $form->get('isAdmin')->getData();

            if ($isEditingOwnAdminAccount && !$isAdmin) {
                $isAdmin = true;
                $this->addFlash('error', 'You cannot remove administrator privileges from your own account.');
            }

            // Password
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            // Avatar Upload
            $avatarFile = $form->get('avatarFile')->getData();
            $clearAvatar = $request->request->get('clearAvatar') === '1';
            if ($avatarFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                $oldAvatar = $user->getAvatar();
                if ($oldAvatar && file_exists($uploadsDir . '/' . $oldAvatar)) {
                    unlink($uploadsDir . '/' . $oldAvatar);
                }
                $newFilename = uniqid() . '.' . $avatarFile->guessExtension();
                $avatarFile->move($uploadsDir, $newFilename);
                $user->setAvatar($newFilename);
            } elseif ($clearAvatar) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                $oldAvatar = $user->getAvatar();
                if ($oldAvatar && file_exists($uploadsDir . '/' . $oldAvatar)) {
                    unlink($uploadsDir . '/' . $oldAvatar);
                }
                $user->setAvatar(null);
            }

            // Clear old permissions first
            foreach ($user->getPermissionUsers() as $permUser) {
                $entityManager->remove($permUser);
            }
            $entityManager->flush();

            // Roles & Permissions
            if ($isAdmin) {
                $user->setRoles(['ROLE_ADMIN']);
            } else {
                $user->setRoles(['ROLE_USER']);
                
                // Save new Permissions
                $selectedPerms = $form->get('permissions')->getData();
                foreach ($selectedPerms as $permission) {
                    $permUser = new PermissionUser();
                    $permUser->setUser($user);
                    $permUser->setPermission($permission);
                    $entityManager->persist($permUser);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', sprintf('User %s has been successfully updated.', $user->getUsername()));
            return $this->redirectToRoute('app_users_index');
        }

        return $this->render('users/edit.html.twig', [
            'form' => $form->createView(),
            'targetUser' => $user,
            'user' => $this->getUser(),
            'is_self_admin_edit' => $isEditingOwnAdminAccount,
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Users', 'url' => $this->generateUrl('app_users_index')],
                ['label' => 'Edit User', 'url' => $this->generateUrl('app_users_edit', ['id' => $user->getId()])],
            ],
        ]);
    }

    #[Route('/{id}/delete', name: 'app_users_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->denyUnlessAdmin();

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_users_index');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $adminsCount = count($userRepository->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%ROLE_ADMIN%')
                ->getQuery()
                ->getResult());
            
            if ($adminsCount <= 1) {
                $this->addFlash('error', 'You cannot delete the only administrator account.');
                return $this->redirectToRoute('app_users_index');
            }
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->getPayload()->getString('_token'))) {
            // Unlink avatar if it exists
            $avatar = $user->getAvatar();
            if ($avatar) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (file_exists($uploadsDir . '/' . $avatar)) {
                    unlink($uploadsDir . '/' . $avatar);
                }
            }

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'User has been successfully deleted.');
        }

        return $this->redirectToRoute('app_users_index');
    }
}
