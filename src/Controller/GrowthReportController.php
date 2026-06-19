<?php

namespace App\Controller;

use App\Service\OpenRouterService;
use App\Repository\SaleRepository;
use App\Repository\PurchaseRepository;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;

#[IsGranted('ROLE_USER')]
final class GrowthReportController extends AbstractController
{
    #[Route('/sales/overview/growth-report', name: 'app_sales_growth_report', methods: ['GET'])]
    public function generateReport(
        Request $request,
        OpenRouterService $openRouterService,
        SaleRepository $saleRepository,
        PurchaseRepository $purchaseRepository
    ): Response {
        if (!$this->isGranted('see.sale.overview')) {
            throw $this->createAccessDeniedException('You do not have permission to view sales overview.');
        }

        $startDateStr = $request->query->get('date', (new \DateTime('-30 days'))->format('Y-m-d'));
        $endDateStr = $request->query->get('end', (new \DateTime())->format('Y-m-d'));

        // Query metrics matching the selected period
        $totalSales = $saleRepository->totalCollectedByDate($startDateStr, $endDateStr) ?? 0;
        $totalPurchases = $purchaseRepository->totalPaidByDate($startDateStr, $endDateStr) ?? 0;
        $totalRefundedSales = $saleRepository->totalRefundedByDate($startDateStr, $endDateStr) ?? 0;
        $totalRefundedPurchases = $purchaseRepository->totalRefundedByDate($startDateStr, $endDateStr) ?? 0;
        $totalOutstandingSales = $saleRepository->totalOutstandingByDate($startDateStr, $endDateStr) ?? 0;
        $totalOutstandingPurchases = $purchaseRepository->totalOutstandingByDate($startDateStr, $endDateStr) ?? 0;

        $metrics = [
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'total_sales' => $totalSales,
            'total_purchases' => $totalPurchases,
            'total_refunded_sales' => $totalRefundedSales,
            'total_refunded_purchases' => $totalRefundedPurchases,
            'total_outstanding_sales' => $totalOutstandingSales,
            'total_outstanding_purchases' => $totalOutstandingPurchases,
        ];

        // Generate report from OpenRouter
        $reportText = $openRouterService->generateReport($metrics);

        // Cache report in session for download route
        $request->getSession()->set('latest_growth_report', [
            'content' => $reportText,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
        ]);

        return $this->json([
            'success' => strpos($reportText, 'Error') !== 0,
            'report' => $reportText
        ]);
    }

    #[Route('/sales/overview/growth-report/download', name: 'app_sales_growth_report_download', methods: ['GET'])]
    public function downloadReport(
        Request $request,
        SettingRepository $settingRepository
    ): Response {
        if (!$this->isGranted('see.sale.overview')) {
            throw $this->createAccessDeniedException('You do not have permission to view sales overview.');
        }

        $sessionData = $request->getSession()->get('latest_growth_report');
        if (!$sessionData) {
            $this->addFlash('error', 'Please generate the growth report on the dashboard first before downloading.');
            return $this->redirectToRoute('app_sales_overview');
        }

        $rawContent = $sessionData['content'];
        $parsedContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $rawContent);
        $parsedContent = preg_replace('/### (.*?)\n/', '<h3 style="color:#4f46e5; margin-top:16px;">$1</h3>', $parsedContent);
        $parsedContent = preg_replace('/## (.*?)\n/', '<h2 style="color:#0f172a; margin-top:20px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">$1</h2>', $parsedContent);
        $parsedContent = preg_replace('/- (.*?)\n/', '<li style="margin-bottom:6px;">$1</li>', $parsedContent);
        $parsedContent = nl2br($parsedContent);

        $html = $this->renderView('invoice/growth_report_pdf.html.twig', [
            'content' => $parsedContent,
            'start_date' => $sessionData['start_date'],
            'end_date' => $sessionData['end_date'],
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'company_address' => $settingRepository->findOneBy(['keyName' => 'company_address'])?->getValue(),
            'company_email' => $settingRepository->findOneBy(['keyName' => 'company_email'])?->getValue(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="AI-growth-report-' . $sessionData['start_date'] . '_to_' . $sessionData['end_date'] . '.pdf"',
        ]);
    }
}
