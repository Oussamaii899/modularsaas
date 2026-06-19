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

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(UserRepository $userRepository, SaleRepository $saleRepository, SettingRepository $settingRepository, PurchaseRepository $purchaseRepository ): Response
    {
        if (!$this->isGranted('see.dashboard')) {
            throw $this->createAccessDeniedException('You do not have permission to view the dashboard.');
        }

        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'user' => $this->getUser() ,
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName'=> 'company_name'])?->getValue(),
            'total_sales' => $saleRepository->totalCollectedByDate('-30 days', 'now') ?? 0,
            'total_purchases' => $purchaseRepository->totalPaidByDate('-30 days', 'now') ?? 0,
            'total_refunded_sales' => $saleRepository->totalRefundedByDate('-30 days', 'now') ?? 0,
            'total_refunded_purchases' => $purchaseRepository->totalRefundedByDate('-30 days', 'now') ?? 0,
            'total_outstanding_sales' => $saleRepository->totalOutstandingByDate('-30 days', 'now') ?? 0,
            'total_outstanding_purchases' => $purchaseRepository->totalOutstandingByDate('-30 days', 'now') ?? 0,
        ]);
    }

    #[Route('/dashboard/data', name:'dashboard_data')]
    public function data(SaleRepository $saleRepository, Request $request, PurchaseRepository $purchaseRepository, SaleItemRepository $saleItemRepository, ProductRepository $productRepository){
        if (!$this->isGranted('see.dashboard')) {
            throw $this->createAccessDeniedException('You do not have permission to view the dashboard data.');
        }
        try {
            $startDate = $request->query->get('date', (new \DateTime('-30 days'))->format('Y-m-d'));
            $endDate = $request->query->get('end', (new \DateTime())->format('Y-m-d'));

            $total_sales = $saleRepository->totalCollectedByDate($startDate, $endDate);
            $total_purchases = $purchaseRepository->totalPaidByDate($startDate, $endDate);
            $total_refunded_sales = $saleRepository->totalRefundedByDate($startDate, $endDate);
            $total_refunded_purchases = $purchaseRepository->totalRefundedByDate($startDate, $endDate);
            $total_outstanding_sales = $saleRepository->totalOutstandingByDate($startDate, $endDate);
            $total_outstanding_purchases = $purchaseRepository->totalOutstandingByDate($startDate, $endDate);

            $sales = $saleRepository->salesByDate($startDate, $endDate);
            $purchases = $purchaseRepository->purchasesByDate($startDate, $endDate);
            
            $salesByProduct = $saleItemRepository->salesByProduct($startDate, $endDate);

            $lowStock = $productRepository->findLowStock(10);

            $recentSales = $saleRepository->findRecent($startDate, $endDate, 5);
            $recentPurchases = $purchaseRepository->findRecent($startDate, $endDate, 5);

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
                    'name' => $p['name'],
                    'price' => $p['price'],
                    'stockQuantity' => $p['stockQuantity'],
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
