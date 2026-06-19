<?php

namespace App\Controller;

use App\Entity\Log;
use App\Repository\LogRepository;
use App\Repository\UserRepository;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/logs')]
#[IsGranted('ROLE_USER')]
class LogsController extends AbstractController
{
    #[Route(name: 'app_logs_index', methods: ['GET'])]
    public function index(
        Request $request, 
        LogRepository $logRepository, 
        UserRepository $userRepository,
        SettingRepository $settingRepository
    ): Response {
        if (!$this->isGranted('see.logs')) {
            throw $this->createAccessDeniedException('You do not have permission to view audit logs.');
        }

        // Get filter inputs
        $searchQuery = $request->query->get('q');
        $actionFilter = $request->query->get('action');
        $userIdFilter = $request->query->has('user') && $request->query->get('user') !== '' ? $request->query->getInt('user') : null;
        
        // Sorting inputs
        $sortField = $request->query->get('sort', 'createdAt');
        $sortOrder = $request->query->get('direction', 'DESC');
        
        $page = $request->query->getInt('page', 1);

        $data = $logRepository->searchAndPaginateLogs(
            $searchQuery,
            $actionFilter,
            $userIdFilter,
            $sortField,
            $sortOrder,
            $page,
            10
        );

        $users = $userRepository->findBy([], ['username' => 'ASC']);

        return $this->render('logs/index.html.twig', [
            'logs' => $data['items'],
            'pagesCount' => $data['pagesCount'],
            'currentPage' => $data['currentPage'],
            'totalItems' => $data['totalItems'],
            'searchQuery' => $searchQuery,
            'actionFilter' => $actionFilter,
            'userIdFilter' => $userIdFilter,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'users' => $users,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Audit Logs', 'url' => $this->generateUrl('app_logs_index')],
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_logs_show', methods: ['GET'])]
    public function show(
        Log $log,
        SettingRepository $settingRepository
    ): Response {
        if (!$this->isGranted('see.logs')) {
            throw $this->createAccessDeniedException('You do not have permission to view audit logs.');
        }

        $beforeData = $log->getBeforeData() ?? [];
        $afterData = $log->getAfterData() ?? [];
        $fields = array_values(array_unique(array_merge(array_keys($beforeData), array_keys($afterData))));

        // Convert FQN class name to human-readable form: "App\Entity\PermissionUser" → "Permission User"
        $rawShortClass = $log->getEntityClass() ? (new \ReflectionClass($log->getEntityClass()))->getShortName() : 'N/A';
        $entityHumanName = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $rawShortClass);

        return $this->render('logs/show.html.twig', [
            'log' => $log,
            'fields' => $fields,
            'entityHumanName' => $entityHumanName,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Audit Logs', 'url' => $this->generateUrl('app_logs_index')],
                ['label' => 'Log Details', 'url' => $this->generateUrl('app_logs_show', ['id' => $log->getId()])],
            ],
        ]);
    }
}
