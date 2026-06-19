<?php

namespace App\Controller;

use App\Entity\Sale;
use App\Entity\Purchase;
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
final class InvoiceController extends AbstractController
{
    #[Route('/sales/invoice/{slug}', name: 'app_sale_invoice', methods: ['GET'])]
    public function saleInvoice(string $slug, SaleRepository $saleRepository, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('see.sale.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view sales invoices.');
        }

        $sale = $saleRepository->findOneBy(['slug' => $slug]);
        if (!$sale) {
            throw $this->createNotFoundException('Sale not found for slug: ' . $slug);
        }

        $html = $this->renderView('invoice/sale_invoice.html.twig', [
            'sale' => $sale,
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
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
            'Content-Disposition' => 'attachment; filename="invoice-' . $slug . '.pdf"',
        ]);
    }

    #[Route('/purchases/invoice/{slug}', name: 'app_purchase_invoice', methods: ['GET'])]
    public function purchaseInvoice(string $slug, PurchaseRepository $purchaseRepository, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('see.purchase.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view purchase invoices.');
        }

        $purchase = $purchaseRepository->findOneBy(['slug' => $slug]);
        if (!$purchase) {
            throw $this->createNotFoundException('Purchase not found for slug: ' . $slug);
        }

        $html = $this->renderView('invoice/purchase_invoice.html.twig', [
            'purchase' => $purchase,
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
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
            'Content-Disposition' => 'attachment; filename="purchase-' . $slug . '.pdf"',
        ]);
    }

    #[Route('/dashboard/export', name: 'app_dashboard_export', methods: ['GET'])]
    public function exportPeriodReport(
        Request $request,
        SaleRepository $saleRepository,
        PurchaseRepository $purchaseRepository,
        SettingRepository $settingRepository
    ): Response {
        if (!$this->isGranted('see.dashboard')) {
            throw $this->createAccessDeniedException('You do not have permission to export dashboard data.');
        }

        $startDateStr = $request->query->get('date', (new \DateTime('-30 days'))->format('Y-m-d'));
        $endDateStr = $request->query->get('end', (new \DateTime())->format('Y-m-d'));

        $totalSales = $saleRepository->totalCollectedByDate($startDateStr, $endDateStr) ?? 0;
        $totalPurchases = $purchaseRepository->totalPaidByDate($startDateStr, $endDateStr) ?? 0;
        $totalRefundedSales = $saleRepository->totalRefundedByDate($startDateStr, $endDateStr) ?? 0;
        $totalRefundedPurchases = $purchaseRepository->totalRefundedByDate($startDateStr, $endDateStr) ?? 0;
        $totalOutstandingSales = $saleRepository->totalOutstandingByDate($startDateStr, $endDateStr) ?? 0;
        $totalOutstandingPurchases = $purchaseRepository->totalOutstandingByDate($startDateStr, $endDateStr) ?? 0;

        // Fetch detailed transactions for matching in the table
        $salesList = $saleRepository->findRecent($startDateStr, $endDateStr, 100);
        $purchasesList = $purchaseRepository->findRecent($startDateStr, $endDateStr, 100);

        $html = $this->renderView('invoice/period_report.html.twig', [
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'total_sales' => $totalSales,
            'total_purchases' => $totalPurchases,
            'total_refunded_sales' => $totalRefundedSales,
            'total_refunded_purchases' => $totalRefundedPurchases,
            'total_outstanding_sales' => $totalOutstandingSales,
            'total_outstanding_purchases' => $totalOutstandingPurchases,
            'sales_list' => $salesList,
            'purchases_list' => $purchasesList,
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
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
            'Content-Disposition' => 'attachment; filename="financial-report-' . $startDateStr . '_to_' . $endDateStr . '.pdf"',
        ]);
    }
}
