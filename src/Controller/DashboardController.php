<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\SettingRepository;
use App\Repository\SaleRepository;
use App\Repository\PurchaseRepository;
use App\Repository\SaleItemRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(
        UserRepository $userRepository,
        SaleRepository $saleRepository,
        SettingRepository $settingRepository,
        PurchaseRepository $purchaseRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isGranted('see.dashboard')) {
            throw $this->createAccessDeniedException('You do not have permission to view the dashboard.');
        }

        $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';

        $doctorStats = [];
        if ($activeModule === 'doctor') {
            $user = $this->getUser();
            $doctorId = $this->isGranted('ROLE_ADMIN') ? null : $user->getId();

            // Total Patients
            $totalPatients = $entityManager->getRepository(\App\Entity\Contact::class)->count(['type' => 'client']);

            // Consultations this month
            $startOfMonth = new \DateTime('first day of this month 00:00:00');
            $endOfMonth = new \DateTime('last day of this month 23:59:59');

            $qb = $entityManager->getRepository(\App\Entity\Sale::class)->createQueryBuilder('s')
                ->select('COUNT(s.id) as count, SUM(s.total) as total')
                ->where('s.created_at >= :start')
                ->andWhere('s.created_at <= :end')
                ->setParameter('start', $startOfMonth)
                ->setParameter('end', $endOfMonth);

            if ($doctorId) {
                $qb->andWhere('s.doctor = :doctorId')
                   ->setParameter('doctorId', $doctorId);
            }
            $consultationsStats = $qb->getQuery()->getSingleResult();

            // Pending payment count
            $qbPending = $entityManager->getRepository(\App\Entity\Sale::class)->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.paymentStatus IN (:statuses)')
                ->setParameter('statuses', ['Unpaid', 'Partial']);

            if ($doctorId) {
                $qbPending->andWhere('s.doctor = :doctorId')
                          ->setParameter('doctorId', $doctorId);
            }
            $pendingPaymentsCount = $qbPending->getQuery()->getSingleScalarResult();

            // Income this month (sum of payments this month)
            $qbIncome = $entityManager->getRepository(\App\Entity\Payment::class)->createQueryBuilder('p')
                ->select('SUM(p.amount)')
                ->join('p.sale', 's')
                ->where('p.createdAt >= :start')
                ->andWhere('p.createdAt <= :end')
                ->setParameter('start', $startOfMonth)
                ->setParameter('end', $endOfMonth);

            if ($doctorId) {
                $qbIncome->andWhere('s.doctor = :doctorId')
                         ->setParameter('doctorId', $doctorId);
            }
            $incomeThisMonth = $qbIncome->getQuery()->getSingleScalarResult() ?? 0;

            // Upcoming appointments today
            $todayStart = new \DateTime('today 00:00:00');
            $todayEnd = new \DateTime('today 23:59:59');

            $qbAppt = $entityManager->getRepository(\App\Entity\Appointment::class)->createQueryBuilder('a')
                ->where('a.startAt >= :start')
                ->andWhere('a.startAt <= :end')
                ->setParameter('start', $todayStart)
                ->setParameter('end', $todayEnd)
                ->orderBy('a.startAt', 'ASC');

            if ($doctorId) {
                $qbAppt->andWhere('a.doctor = :doctorId')
                       ->setParameter('doctorId', $doctorId);
            }
            $appointmentsToday = $qbAppt->getQuery()->getResult();

            $doctorStats = [
                'total_patients' => $totalPatients,
                'consultations_count' => $consultationsStats['count'] ?? 0,
                'pending_payments_count' => $pendingPaymentsCount,
                'income_this_month' => $incomeThisMonth,
                'appointments_today' => $appointmentsToday,
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName'=> 'company_name'])?->getValue(),
            'total_sales' => $saleRepository->totalCollectedByDate('-30 days', 'now') ?? 0,
            'total_purchases' => $purchaseRepository->totalPaidByDate('-30 days', 'now') ?? 0,
            'total_refunded_sales' => $saleRepository->totalRefundedByDate('-30 days', 'now') ?? 0,
            'total_refunded_purchases' => $purchaseRepository->totalRefundedByDate('-30 days', 'now') ?? 0,
            'total_outstanding_sales' => $saleRepository->totalOutstandingByDate('-30 days', 'now') ?? 0,
            'total_outstanding_purchases' => $purchaseRepository->totalOutstandingByDate('-30 days', 'now') ?? 0,
            'active_module' => $activeModule,
            'doctor_stats' => $doctorStats,
        ]);
    }

    #[Route('/dashboard/data', name:'dashboard_data')]
    public function data(
        SaleRepository $saleRepository,
        Request $request,
        PurchaseRepository $purchaseRepository,
        SaleItemRepository $saleItemRepository,
        ProductRepository $productRepository,
        SettingRepository $settingRepository,
        EntityManagerInterface $entityManager
    ){
        if (!$this->isGranted('see.dashboard')) {
            throw $this->createAccessDeniedException('You do not have permission to view the dashboard data.');
        }
        try {
            $startDate = $request->query->get('date', (new \DateTime('-30 days'))->format('Y-m-d'));
            $endDate = $request->query->get('end', (new \DateTime())->format('Y-m-d'));

            $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';
            $user = $this->getUser();
            $doctorId = ($activeModule === 'doctor' && !$this->isGranted('ROLE_ADMIN')) ? $user->getId() : null;

            $total_sales = $saleRepository->totalCollectedByDate($startDate, $endDate, $doctorId);
            $total_purchases = $doctorId ? 0 : $purchaseRepository->totalPaidByDate($startDate, $endDate);
            $total_refunded_sales = $saleRepository->totalRefundedByDate($startDate, $endDate, $doctorId);
            $total_refunded_purchases = $doctorId ? 0 : $purchaseRepository->totalRefundedByDate($startDate, $endDate);
            $total_outstanding_sales = $saleRepository->totalOutstandingByDate($startDate, $endDate, $doctorId);
            $total_outstanding_purchases = $doctorId ? 0 : $purchaseRepository->totalOutstandingByDate($startDate, $endDate);

            $sales = $saleRepository->salesByDate($startDate, $endDate, $doctorId);
            $purchases = $doctorId ? [] : $purchaseRepository->purchasesByDate($startDate, $endDate);
            
            $salesByProduct = $saleItemRepository->salesByProduct($startDate, $endDate, $doctorId);

            $lowStock = $productRepository->findLowStock(10);

            $recentSales = $saleRepository->findRecent($startDate, $endDate, 5, $doctorId);
            $recentPurchases = $doctorId ? [] : $purchaseRepository->findRecent($startDate, $endDate, 5);

            $formattedSales = [];
            foreach ($sales as $s) {
                $formattedSales[] = [
                    'total' => $s['total'] ?? 0,
                    'created_at' => ['date' => $s['date'] . ' 00:00:00']
                ];
            }

            $formattedPurchases = [];
            foreach ($purchases as $p) {
                $formattedPurchases[] = [
                    'total' => $p['total'] ?? 0,
                    'created_at' => ['date' => $p['date'] . ' 00:00:00']
                ];
            }

            $recentTransactions = [];
            foreach ($recentSales as $s) {
                $createdAt = $s->getCreatedAt();
                $recentTransactions[] = [
                    'id' => $s->getId(),
                    'slug' => $s->getSlug(),
                    'type' => 'sale',
                    'total' => $s->getTotal(),
                    'paymentStatus' => $s->getPaymentStatus(),
                    'created_at' => ['date' => $createdAt ? $createdAt->format('Y-m-d H:i:s') : ''],
                    'contact' => ['name' => $s->getContact() ? $s->getContact()->getName() : 'Unknown']
                ];
            }
            foreach ($recentPurchases as $p) {
                $createdAt = $p->getCreatedAt();
                $recentTransactions[] = [
                    'id' => $p->getId(),
                    'slug' => $p->getSlug(),
                    'type' => 'purchase',
                    'total' => $p->getTotal(),
                    'paymentStatus' => $p->getPaymentStatus(),
                    'created_at' => ['date' => $createdAt ? $createdAt->format('Y-m-d H:i:s') : ''],
                    'contact' => ['name' => $p->getContact() ? $p->getContact()->getName() : 'Unknown']
                ];
            }

            usort($recentTransactions, function($a, $b) {
                return $b['created_at']['date'] <=> $a['created_at']['date'];
            });

            $recentTransactions = array_slice($recentTransactions, 0, 6);

            $formattedLowStock = [];
            foreach ($lowStock as $p) {
                $formattedLowStock[] = [
                    'id' => $p['id'],
                    'slug' => $p['slug'] ?? null,
                    'name' => $p['name'],
                    'price' => $p['price'],
                    'stockQuantity' => $p['stockQuantity'],
                ];
            }

            $doctorStats = [];
            if ($activeModule === 'doctor') {
                $totalPatients = $entityManager->getRepository(\App\Entity\Contact::class)->count(['type' => 'client']);

                $qb = $entityManager->getRepository(\App\Entity\Sale::class)->createQueryBuilder('s')
                    ->select('COUNT(s.id) as count, SUM(s.total) as total')
                    ->where('s.created_at >= :start')
                    ->andWhere('s.created_at <= :end')
                    ->setParameter('start', new \DateTime($startDate . ' 00:00:00'))
                    ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));

                if ($doctorId) {
                    $qb->andWhere('s.doctor = :doctorId')
                       ->setParameter('doctorId', $doctorId);
                }
                $consultationsStats = $qb->getQuery()->getSingleResult();

                $qbPending = $entityManager->getRepository(\App\Entity\Sale::class)->createQueryBuilder('s')
                    ->select('COUNT(s.id)')
                    ->where('s.paymentStatus IN (:statuses)')
                    ->andWhere('s.created_at >= :start')
                    ->andWhere('s.created_at <= :end')
                    ->setParameter('statuses', ['Unpaid', 'Partial'])
                    ->setParameter('start', new \DateTime($startDate . ' 00:00:00'))
                    ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));

                if ($doctorId) {
                    $qbPending->andWhere('s.doctor = :doctorId')
                              ->setParameter('doctorId', $doctorId);
                }
                $pendingPaymentsCount = $qbPending->getQuery()->getSingleScalarResult();

                $qbIncome = $entityManager->getRepository(\App\Entity\Payment::class)->createQueryBuilder('p')
                    ->select('SUM(p.amount)')
                    ->join('p.sale', 's')
                    ->where('p.createdAt >= :start')
                    ->andWhere('p.createdAt <= :end')
                    ->setParameter('start', new \DateTime($startDate . ' 00:00:00'))
                    ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));

                if ($doctorId) {
                    $qbIncome->andWhere('s.doctor = :doctorId')
                             ->setParameter('doctorId', $doctorId);
                }
                $incomeThisRange = $qbIncome->getQuery()->getSingleScalarResult() ?? 0;

                $doctorStats = [
                    'total_patients' => $totalPatients,
                    'consultations_count' => $consultationsStats['count'] ?? 0,
                    'pending_payments_count' => $pendingPaymentsCount,
                    'income_this_month' => $incomeThisRange,
                ];
            }

            return $this->json([
                'total_sales' => $total_sales ?? 0,
                'total_purchases' => $total_purchases ?? 0,
                'total_refunded_sales' => $total_refunded_sales ?? 0,
                'total_refunded_purchases' => $total_refunded_purchases ?? 0,
                'total_outstanding_sales' => $total_outstanding_sales ?? 0,
                'total_outstanding_purchases' => $total_outstanding_purchases ?? 0,
                'sales' => $formattedSales,
                'purchases' => $formattedPurchases,
                'salesByProduct' => $salesByProduct,
                'lowStock' => $formattedLowStock,
                'recentTransactions' => $recentTransactions,
                'doctor_stats' => $doctorStats,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/settings/toggle-theme-db', name: 'app_settings_toggle_theme_db', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleThemeDb(Request $request, SettingRepository $settingRepository, \Doctrine\ORM\EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);
        $theme = $data['theme'] ?? 'light';

        if (in_array($theme, ['light', 'dark'])) {
            $setting = $settingRepository->findOneBy(['keyName' => 'theme']);
            if (!$setting) {
                $setting = new \App\Entity\Setting();
                $setting->setKeyName('theme');
                $entityManager->persist($setting);
            }
            $setting->setValue($theme);
            $entityManager->flush();

            return $this->json(['success' => true]);
        }

        return $this->json(['success' => false, 'message' => 'Invalid theme value'], 400);
    }
}
