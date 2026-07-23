<?php

namespace App\Controller;

use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Payment;
use App\Entity\PrescriptionItem;
use App\Entity\Product;
use App\Form\SaleType;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Repository\SettingRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sales')]
#[IsGranted('ROLE_USER')]
final class SalesController extends AbstractController
{
    #[Route('/overview', name: 'app_sales_overview', methods: ['GET'])]
    public function overview(SaleRepository $saleRepository, SettingRepository $settingRepository, \App\Repository\ContactRepository $contactRepository): Response
    {
        if (!$this->isGranted('see.sale.overview')) {
            throw $this->createAccessDeniedException('You do not have permission to view the sales overview.');
        }
        $recentSales = $saleRepository->findBy([], ['created_at' => 'DESC'], 30);
        $recentContacts = [];
        foreach ($recentSales as $sale) {
            $contact = $sale->getContact();
            if ($contact && !isset($recentContacts[$contact->getId()])) {
                $recentContacts[$contact->getId()] = $contact;
                if (count($recentContacts) >= 5) {
                    break;
                }
            }
        }
        if (count($recentContacts) < 5) {
            $allClients = $contactRepository->findBy(['type' => 'client'], ['id' => 'DESC'], 10);
            foreach ($allClients as $client) {
                if (!isset($recentContacts[$client->getId()])) {
                    $recentContacts[$client->getId()] = $client;
                    if (count($recentContacts) >= 5) {
                        break;
                    }
                }
            }
        }

        return $this->render('sales/overview.html.twig', [
            'total_gross_30' => $saleRepository->totalCollectedByDate('-30 days', 'now') ?? 0,
            'total_refunds_30' => abs($saleRepository->totalRefundedByDate('-30 days', 'now') ?? 0),
            'total_net_30' => $saleRepository->totalNetCollectedByDate('-30 days', 'now') ?? 0,
            'total_outstanding' => $saleRepository->totalOutstandingByDate('2000-01-01', 'now'),
            'total_sales_today' => $saleRepository->totalCollectedByDate('today', 'now') ?? 0,
            'top_clients' => array_values($recentContacts),
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => '#'],
                ['label' => 'Overview', 'url' => $this->generateUrl('app_sales_overview')],
            ],
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
        ]);
    }

    #[Route(name: 'app_sales_index', methods: ['GET'])]
    public function index(Request $request, SaleRepository $saleRepository, SettingRepository $settingRepository, \App\Repository\ContactRepository $contactRepository): Response
    {
        if (!$this->isGranted('see.sale.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view the sales list.');
        }
        $pageVal = $request->query->get('page', 1);
        $page = filter_var($pageVal, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?: 1;
        if ($page < 1) {
            $page = 1;
        }
        $query = $request->query->get('q');
        $status = $request->query->get('status');
        $contactIdVal = $request->query->get('contact_id');
        $filterContact = null;
        if ($contactIdVal) {
            if (is_numeric($contactIdVal)) {
                $filterContact = $contactRepository->find((int)$contactIdVal);
            } else {
                $filterContact = $contactRepository->findOneBy(['slug' => $contactIdVal]);
            }
        }
        $contactId = $filterContact ? $filterContact->getId() : null;
        $sort = $request->query->get('sort', 'newest');

        $sortBy = 'created_at';
        $sortDir = 'DESC';

        if ($sort === 'oldest') {
            $sortDir = 'ASC';
        } elseif ($sort === 'total_desc') {
            $sortBy = 'total';
            $sortDir = 'DESC';
        } elseif ($sort === 'total_asc') {
            $sortBy = 'total';
            $sortDir = 'ASC';
        }

        $data = $saleRepository->searchAndPaginate($query, $page, 10, $status, $contactId, $sortBy, $sortDir);

        return $this->render('sales/index.html.twig', [
            'sales' => $data['items'],
            'pagesCount' => $data['pagesCount'],
            'currentPage' => $data['currentPage'],
            'totalItems' => $data['totalItems'],
            'searchQuery' => $query,
            'statusFilter' => $status,
            'sortFilter' => $sort,
            'contactIdFilter' => $contactIdVal,
            'filterContact' => $filterContact,
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => '#'],
                ['label' => 'Sales List', 'url' => $this->generateUrl('app_sales_index')],
            ],
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
        ]);
    }

    #[Route('/new', name: 'app_sales_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('add.sales')) {
            throw $this->createAccessDeniedException('You do not have permission to add sales.');
        }
        // Create new Sale and handle form
        $sale = new Sale();
        $form = $this->createForm(SaleType::class, $sale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';

            foreach ($sale->getSaleItems() as $item) {
                if ($item->getProduct()) {
                    $item->setPName($item->getProduct()->getName());
                    $item->setPSku($item->getProduct()->getSku());

                    if ($activeModule === 'doctor') {
                        $item->setPrice(0.00);
                    }

                    $product = $item->getProduct();
                    if ($product->isSerialized()) {
                        $productItemRepo = $entityManager->getRepository(\App\Entity\ProductItem::class);
                        $availableItems = $productItemRepo->findAvailableItemsByProduct($product->getId(), $item->getQuantity());
                        
                        foreach ($availableItems as $pItem) {
                            $pItem->setStatus(\App\Entity\ProductItem::STATUS_SOLD);
                            $pItem->setSaleItem($item);
                            $entityManager->persist($pItem);
                        }
                    } else {
                        $product->setStockQuantity($product->getStockQuantity() - $item->getQuantity());
                        $entityManager->persist($product);
                    }
                }
                if ($sale->getContact()) {
                    $item->setContact($sale->getContact());
                    $item->setContactName($sale->getContact()->getName());
                }
            }
            
            if ($activeModule === 'doctor') {
                $sale->setMedicalDetails($request->request->get('medicalDetails'));
                $sale->setPrescriptionNotes($request->request->get('prescriptionNotes'));
                $sale->setDoctor($this->getUser());

                // Handle structured prescription items
                $rxNames = $request->request->all('rx_name');
                $rxDosages = $request->request->all('rx_dosage');
                $rxFrequencies = $request->request->all('rx_frequency');
                $rxDurations = $request->request->all('rx_duration');
                $rxInstructions = $request->request->all('rx_instructions');
                $rxQuantities = $request->request->all('rx_quantity');

                foreach ($rxNames as $i => $name) {
                    if (empty(trim($name))) continue;
                    $pi = new PrescriptionItem();
                    $pi->setMedicationName(trim($name));
                    $pi->setDosage($rxDosages[$i] ?? null);
                    $pi->setFrequency($rxFrequencies[$i] ?? null);
                    $pi->setDuration($rxDurations[$i] ?? null);
                    $pi->setInstructions($rxInstructions[$i] ?? null);
                    $pi->setQuantity(!empty($rxQuantities[$i]) ? (int)$rxQuantities[$i] : null);
                    $sale->addPrescriptionItem($pi);
                    $entityManager->persist($pi);
                }
            }

            $entityManager->persist($sale);

            $recordPayment = $request->request->get('record_payment');
            $paymentAmount = $request->request->get('payment_amount');
            if ($recordPayment && $paymentAmount > 0) {
                $payment = new Payment();
                $payment->setAmount($paymentAmount);
                $payment->setMethod($request->request->get('payment_method', 'Cash'));
                $payment->setReference($request->request->get('payment_reference'));
                $payment->setSale($sale);
                $entityManager->persist($payment);

                $sale->addPayment($payment);
            }

            $sale->updatePaymentStatus();

            $entityManager->flush();

            return $this->redirectToRoute('app_sales_index', [], Response::HTTP_SEE_OTHER);
        }

        $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';

        return $this->render('sales/new.html.twig', [
            'sale' => $sale,
            'form' => $form->createView(),
            'active_module' => $activeModule,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => $this->generateUrl('app_sales_index')],
                ['label' => 'Register Sale', 'url' => $this->generateUrl('app_sales_new')],
            ],
        ]);
    }

    #[Route('/{slug}', name: 'app_sales_show', methods: ['GET'])]
    public function show(Sale $sale, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('see.sale.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view this sale.');
        }
        $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';
        return $this->render('sales/show.html.twig', [
            'sale' => $sale,
            'active_module' => $activeModule,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => $this->generateUrl('app_sales_index')],
                ['label' => 'INV-' . str_pad($sale->getId(), 4, '0', STR_PAD_LEFT), 'url' => $this->generateUrl('app_sales_show', ['slug' => $sale->getSlug()])],
            ],
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_sales_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sale $sale, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('edit.sales')) {
            throw $this->createAccessDeniedException('You do not have permission to edit sales.');
        }

        if ($sale->getPaymentStatus() === 'Cancelled' || $sale->getPaymentStatus() === 'Refunded') {
            $this->addFlash('error', 'Cancelled or fully refunded sales cannot be edited.');
            return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
        }

        $originalQuantities = [];
        foreach ($sale->getSaleItems() as $item) {
            if ($item->getProduct()) {
                $originalQuantities[$item->getId()] = [
                    'product' => $item->getProduct(),
                    'qty' => $item->getQuantity(),
                ];
            }
        }

        $form = $this->createForm(SaleType::class, $sale);
        $form->handleRequest($request);

        $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($originalQuantities as $data) {
                $product = $data['product'];
                $product->setStockQuantity($product->getStockQuantity() + $data['qty']);
                $entityManager->persist($product);
            }

            if ($sale->getSaleItems()->isEmpty()) {
                if (!$sale->getPayments()->isEmpty()) {
                    $this->addFlash('error', 'Cannot delete sale invoice INV-' . str_pad($sale->getId(), 4, '0', STR_PAD_LEFT) . ' because it has recorded payments. Please delete all recorded payments first.');
                    return $this->redirectToRoute('app_sales_edit', ['id' => $sale->getId()]);
                }

                $saleItemRepository = $entityManager->getRepository(\App\Entity\SaleItem::class);
                $saleItems = $saleItemRepository->findBy(['sale' => $sale]);
                foreach ($saleItems as $item) {
                    $entityManager->remove($item);
                }
                $entityManager->flush();

                $entityManager->remove($sale);
                $entityManager->flush();

                $this->addFlash('success', 'Sale invoice INV-' . str_pad($sale->getId(), 4, '0', STR_PAD_LEFT) . ' was completely deleted because all items were removed.');
                return $this->redirectToRoute('app_sales_index');
            }

            foreach ($sale->getSaleItems() as $item) {
                if ($item->getProduct()) {
                    $item->setPName($item->getProduct()->getName());
                    $item->setPSku($item->getProduct()->getSku());

                    $product = $item->getProduct();
                    $product->setStockQuantity($product->getStockQuantity() - $item->getQuantity());
                    $entityManager->persist($product);
                }
                if ($sale->getContact()) {
                    $item->setContact($sale->getContact());
                    $item->setContactName($sale->getContact()->getName());
                }
            }

            if ($activeModule === 'doctor') {
                $sale->setMedicalDetails($request->request->get('medicalDetails'));
                $sale->setPrescriptionNotes($request->request->get('prescriptionNotes'));

                // Clear old prescription items and re-add
                foreach ($sale->getPrescriptionItems() as $oldPi) {
                    $sale->removePrescriptionItem($oldPi);
                    $entityManager->remove($oldPi);
                }

                $rxNames = $request->request->all('rx_name');
                $rxDosages = $request->request->all('rx_dosage');
                $rxFrequencies = $request->request->all('rx_frequency');
                $rxDurations = $request->request->all('rx_duration');
                $rxInstructions = $request->request->all('rx_instructions');
                $rxQuantities = $request->request->all('rx_quantity');

                foreach ($rxNames as $i => $name) {
                    if (empty(trim($name))) continue;
                    $pi = new PrescriptionItem();
                    $pi->setMedicationName(trim($name));
                    $pi->setDosage($rxDosages[$i] ?? null);
                    $pi->setFrequency($rxFrequencies[$i] ?? null);
                    $pi->setDuration($rxDurations[$i] ?? null);
                    $pi->setInstructions($rxInstructions[$i] ?? null);
                    $pi->setQuantity(!empty($rxQuantities[$i]) ? (int)$rxQuantities[$i] : null);
                    $sale->addPrescriptionItem($pi);
                    $entityManager->persist($pi);
                }
            }

            $sale->updatePaymentStatus();

            $entityManager->flush();

            return $this->redirectToRoute('app_sales_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sales/edit.html.twig', [
            'sale' => $sale,
            'form' => $form->createView(),
            'active_module' => $activeModule,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => $this->generateUrl('app_sales_index')],
                ['label' => 'Edit Record', 'url' => $this->generateUrl('app_sales_edit', ['slug' => $sale->getSlug()])],
            ],
        ]);
    }

    #[Route('/overview/data', name: 'app_sales_overview_data', methods: ['GET'])]
    public function overviewData(SaleRepository $saleRepository, Request $request): Response
    {
        if (!$this->isGranted('see.sale.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view sales overview data.');
        }
        try {
            $startDate = $request->query->get('date', (new \DateTime('-30 days'))->format('Y-m-d'));
            $endDate = $request->query->get('end', (new \DateTime())->format('Y-m-d'));

            $total_gross_30 = $saleRepository->totalCollectedByDate($startDate, $endDate) ?? 0;
            $total_refunds_30 = abs($saleRepository->totalRefundedByDate($startDate, $endDate) ?? 0);
            $total_net_30 = $saleRepository->totalNetCollectedByDate($startDate, $endDate) ?? 0;
            $total_outstanding = $saleRepository->totalOutstandingByDate('2000-01-01', $endDate);

            $sales = $saleRepository->salesByDate($startDate, $endDate);

            $formattedSales = [];
            foreach ($sales as $s) {
                $formattedSales[] = [
                    'total' => $s['total'] ?? 0,
                    'created_at' => ['date' => $s['date'] . ' 00:00:00']
                ];
            }

            return $this->json([
                'total_gross_30' => $total_gross_30,
                'total_refunds_30' => $total_refunds_30,
                'total_net_30' => $total_net_30,
                'total_outstanding' => $total_outstanding,
                'sales' => $formattedSales,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{slug}/cancel', name: 'app_sales_cancel', methods: ['POST'])]
    public function cancel(Sale $sale, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('delete.sales')) {
            throw $this->createAccessDeniedException('You do not have permission to cancel sales.');
        }
        if ($sale->getPaymentStatus() === 'Cancelled' || $sale->getPaymentStatus() === 'Refunded') {
            $this->addFlash('warning', 'This sale is already cancelled or fully refunded.');
            return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
        }

        $paidAmount = $sale->getPaidAmount();

        if ($paidAmount > 0) {
            foreach ($sale->getSaleItems() as $item) {
                if ($item->getStatus() === 'Active') {
                    $item->setStatus('Refunded');
                    $entityManager->persist($item);
                    
                    if ($item->getProduct()) {
                        $product = $item->getProduct();
                        $product->setStockQuantity($product->getStockQuantity() + $item->getQuantity());
                        $entityManager->persist($product);
                    }
                }
            }

            $refund = new Payment();
            $refund->setAmount(-$paidAmount);
            $refund->setMethod('Refund');
            $refund->setReference('Full cancel refund INV-' . str_pad($sale->getId(), 4, '0', STR_PAD_LEFT));
            $refund->setType('Refund');
            $refund->setSale($sale);
            $entityManager->persist($refund);
            $sale->addPayment($refund);

            $sale->setTotal(0.00);

            $sale->updatePaymentStatus();

            $this->addFlash('success', 'Sale INV-' . str_pad($sale->getId(), 4, '0', STR_PAD_LEFT) . ' has been successfully fully refunded and marked as Refunded.');
        } else {
            $sale->setPaymentStatus('Cancelled');

            foreach ($sale->getSaleItems() as $item) {
                if ($item->getStatus() === 'Active') {
                    $item->setStatus('Cancelled');
                    $entityManager->persist($item);
                    if ($item->getProduct()) {
                        $product = $item->getProduct();
                        $product->setStockQuantity($product->getStockQuantity() + $item->getQuantity());
                        $entityManager->persist($product);
                    }
                }
            }

            foreach ($sale->getPayments() as $payment) {
                $entityManager->remove($payment);
            }

            $this->addFlash('success', 'Sale invoice INV-' . str_pad($sale->getId(), 4, '0', STR_PAD_LEFT) . ' has been successfully cancelled and stock was returned.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
    }

    #[Route('/item/{id}/refund', name: 'app_sales_item_refund', methods: ['POST'])]
    public function refundItem(SaleItem $item, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('edit.sales')) {
            throw $this->createAccessDeniedException('You do not have permission to refund sale items.');
        }
        $sale = $item->getSale();
        if ($sale->getPaymentStatus() === 'Cancelled') {
            $this->addFlash('error', 'Cannot refund items for a cancelled sale.');
            return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
        }

        if ($item->getStatus() === 'Refunded') {
            $this->addFlash('warning', 'This item is already refunded.');
            return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
        }

        $item->setStatus('Refunded');
        $entityManager->persist($item);

        if ($item->getProduct()) {
            $product = $item->getProduct();
            $product->setStockQuantity($product->getStockQuantity() + $item->getQuantity());
            $entityManager->persist($product);
        }

        $newTotal = 0.00;
        foreach ($sale->getSaleItems() as $saleItem) {
            if ($saleItem->getStatus() === 'Active') {
                $newTotal += (float) $saleItem->getPrice() * $saleItem->getQuantity();
            }
        }
        $sale->setTotal($newTotal);

        $sale->updatePaymentStatus();

        $entityManager->flush();

        $this->addFlash('success', 'Successfully cancelled & refunded the product: ' . $item->getPName() . '. The grand total was reduced, triggering an overpaid balance.');
        return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
    }
}
