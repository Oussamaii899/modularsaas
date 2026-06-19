<?php

namespace App\Controller;

use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\Payment;
use App\Form\PurchaseType;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Repository\SettingRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/purchases')]
#[IsGranted('ROLE_USER')]
final class PurchasesController extends AbstractController
{
    #[Route('/overview', name: 'app_purchases_overview', methods: ['GET'])]
    public function overview(PurchaseRepository $purchaseRepository, SettingRepository $settingRepository, \App\Repository\ProductRepository $productRepository): Response
    {
        if (!$this->isGranted('see.purchase.overview')) {
            throw $this->createAccessDeniedException('You do not have permission to view the purchases overview.');
        }

        return $this->render('purchases/overview.html.twig', [
            'total_gross_30' => $purchaseRepository->totalPaidByDate('-30 days', 'now') ?? 0,
            'total_refunds_30' => abs($purchaseRepository->totalRefundedByDate('-30 days', 'now') ?? 0),
            'total_net_30' => $purchaseRepository->totalNetPaidByDate('-30 days', 'now') ?? 0,
            'total_outstanding' => $purchaseRepository->totalOutstandingByDate('2000-01-01', 'now'),
            'total_purchases_today' => $purchaseRepository->totalPaidByDate('today', 'now') ?? 0,
            'all_products' => $productRepository->findAll(),
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => '#'],
                ['label' => 'Overview', 'url' => $this->generateUrl('app_purchases_overview')],
            ],
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
        ]);
    }

    #[Route(name: 'app_purchases_index', methods: ['GET'])]
    public function index(Request $request, PurchaseRepository $purchaseRepository, SettingRepository $settingRepository, \App\Repository\ContactRepository $contactRepository): Response
    {
        if (!$this->isGranted('see.purchase.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view the purchase list.');
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

        $data = $purchaseRepository->searchAndPaginate($query, $page, 10, $status, $contactId, $sortBy, $sortDir);

        return $this->render('purchases/index.html.twig', [
            'purchases' => $data['items'],
            'pagesCount' => $data['pagesCount'],
            'currentPage' => $data['currentPage'],
            'totalItems' => $data['totalItems'],
            'searchQuery' => $query,
            'statusFilter' => $status,
            'sortFilter' => $sort,
            'contactIdFilter' => $contactIdVal,
            'filterContact' => $filterContact,
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => '#'],
                ['label' => 'Purchase List', 'url' => $this->generateUrl('app_purchases_index')],
            ],
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
        ]);
    }

    #[Route('/new', name: 'app_purchases_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('add.purchases')) {
            throw $this->createAccessDeniedException('You do not have permission to add purchases.');
        }
        $purchase = new Purchase();
        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($purchase->getPurchaseItems() as $item) {
                if ($item->getProduct()) {
                    $item->setPName($item->getProduct()->getName());
                    $item->setPSku($item->getProduct()->getSku());

                    $product = $item->getProduct();
                    $product->setStockQuantity($product->getStockQuantity() + $item->getQuantity());
                    $entityManager->persist($product);
                }
                if ($purchase->getContact()) {
                    $item->setContact($purchase->getContact());
                    $item->setContactName($purchase->getContact()->getName());
                }
            }
            
            $entityManager->persist($purchase);

            $recordPayment = $request->request->get('record_payment');
            $paymentAmount = $request->request->get('payment_amount');
            if ($recordPayment && $paymentAmount > 0) {
                $payment = new Payment();
                $payment->setAmount($paymentAmount);
                $payment->setMethod($request->request->get('payment_method', 'Cash'));
                $payment->setReference($request->request->get('payment_reference'));
                $payment->setPurchase($purchase);
                $entityManager->persist($payment);

                $purchase->addPayment($payment);
            }

            $purchase->updatePaymentStatus();

            $entityManager->flush();

            return $this->redirectToRoute('app_purchases_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('purchases/new.html.twig', [
            'purchase' => $purchase,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => $this->generateUrl('app_purchases_index')],
                ['label' => 'New Transaction', 'url' => $this->generateUrl('app_purchases_new')],
            ],
        ]);
    }

    #[Route('/{slug}', name: 'app_purchases_show', methods: ['GET'])]
    public function show(Purchase $purchase, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('see.purchase.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view this purchase.');
        }
        return $this->render('purchases/show.html.twig', [
            'purchase' => $purchase,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => $this->generateUrl('app_purchases_index')],
                ['label' => 'PUR-' . str_pad($purchase->getId(), 4, '0', STR_PAD_LEFT), 'url' => $this->generateUrl('app_purchases_show', ['slug' => $purchase->getSlug()])],
            ],
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_purchases_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Purchase $purchase, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('edit.purchases')) {
            throw $this->createAccessDeniedException('You do not have permission to edit purchases.');
        }
        if ($purchase->getPaymentStatus() === 'Cancelled' || $purchase->getPaymentStatus() === 'Refunded') {
            $this->addFlash('error', 'Cancelled or fully refunded purchases cannot be edited.');
            return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
        }

        $originalQuantities = [];
        foreach ($purchase->getPurchaseItems() as $item) {
            if ($item->getProduct()) {
                $originalQuantities[$item->getId()] = [
                    'product' => $item->getProduct(),
                    'qty' => $item->getQuantity(),
                ];
            }
        }

        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($originalQuantities as $data) {
                $product = $data['product'];
                $product->setStockQuantity($product->getStockQuantity() - $data['qty']);
                $entityManager->persist($product);
            }

            if ($purchase->getPurchaseItems()->isEmpty()) {
                if (!$purchase->getPayments()->isEmpty()) {
                    $this->addFlash('error', 'Cannot delete purchase record PUR-' . str_pad($purchase->getId(), 4, '0', STR_PAD_LEFT) . ' because it has recorded payments. Please delete all recorded payments first.');
                    return $this->redirectToRoute('app_purchases_edit', ['id' => $purchase->getId()]);
                }

                $purchaseItemRepository = $entityManager->getRepository(\App\Entity\PurchaseItem::class);
                $purchaseItems = $purchaseItemRepository->findBy(['purchase' => $purchase]);
                foreach ($purchaseItems as $item) {
                    $entityManager->remove($item);
                }
                $entityManager->flush();

                $entityManager->remove($purchase);
                $entityManager->flush();

                $this->addFlash('success', 'Purchase record PUR-' . str_pad($purchase->getId(), 4, '0', STR_PAD_LEFT) . ' was completely deleted because all items were removed.');
                return $this->redirectToRoute('app_purchases_index');
            }

            foreach ($purchase->getPurchaseItems() as $item) {
                if ($item->getProduct()) {
                    $item->setPName($item->getProduct()->getName());
                    $item->setPSku($item->getProduct()->getSku());

                    $product = $item->getProduct();
                    $product->setStockQuantity($product->getStockQuantity() + $item->getQuantity());
                    $entityManager->persist($product);
                }
                if ($purchase->getContact()) {
                    $item->setContact($purchase->getContact());
                    $item->setContactName($purchase->getContact()->getName());
                }
            }

            $purchase->updatePaymentStatus();

            $entityManager->flush();

            return $this->redirectToRoute('app_purchases_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('purchases/edit.html.twig', [
            'purchase' => $purchase,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => $this->generateUrl('app_purchases_index')],
                ['label' => 'Edit Record', 'url' => $this->generateUrl('app_purchases_edit', ['slug' => $purchase->getSlug()])],
            ],
        ]);
    }

    #[Route('/overview/data', name: 'app_purchases_overview_data', methods: ['GET'])]
    public function overviewData(PurchaseRepository $purchaseRepository, Request $request): Response
    {
        if (!$this->isGranted('see.purchase.list')) {
            throw $this->createAccessDeniedException('You do not have permission to view purchases overview data.');
        }
        try {
            $startDate = $request->query->get('date', (new \DateTime('-30 days'))->format('Y-m-d'));
            $endDate = $request->query->get('end', (new \DateTime())->format('Y-m-d'));

            $total_gross_30 = $purchaseRepository->totalPaidByDate($startDate, $endDate) ?? 0;
            $total_refunds_30 = abs($purchaseRepository->totalRefundedByDate($startDate, $endDate) ?? 0);
            $total_net_30 = $purchaseRepository->totalNetPaidByDate($startDate, $endDate) ?? 0;
            $total_outstanding = $purchaseRepository->totalOutstandingByDate('2000-01-01', $endDate);

            $purchases = $purchaseRepository->purchasesByDate($startDate, $endDate);

            $formattedPurchases = [];
            foreach ($purchases as $p) {
                $formattedPurchases[] = [
                    'total' => $p['total'] ?? 0,
                    'created_at' => ['date' => $p['date'] . ' 00:00:00']
                ];
            }

            return $this->json([
                'total_gross_30' => $total_gross_30,
                'total_refunds_30' => $total_refunds_30,
                'total_net_30' => $total_net_30,
                'total_outstanding' => $total_outstanding,
                'purchases' => $formattedPurchases,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{slug}/cancel', name: 'app_purchases_cancel', methods: ['POST'])]
    public function cancel(Purchase $purchase, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('delete.purchases')) {
            throw $this->createAccessDeniedException('You do not have permission to cancel purchases.');
        }
        if ($purchase->getPaymentStatus() === 'Cancelled' || $purchase->getPaymentStatus() === 'Refunded') {
            $this->addFlash('warning', 'This purchase is already cancelled or fully refunded.');
            return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
        }

        $paidAmount = $purchase->getPaidAmount();

        if ($paidAmount > 0) {
            foreach ($purchase->getPurchaseItems() as $item) {
                if ($item->getStatus() === 'Active') {
                    $item->setStatus('Refunded');
                    $entityManager->persist($item);
                    
                    if ($item->getProduct()) {
                        $product = $item->getProduct();
                        $product->setStockQuantity($product->getStockQuantity() - $item->getQuantity());
                        $entityManager->persist($product);
                    }
                }
            }

            $refund = new Payment();
            $refund->setAmount(-$paidAmount);
            $refund->setMethod('Refund');
            $refund->setReference('Full cancel refund PUR-' . str_pad($purchase->getId(), 4, '0', STR_PAD_LEFT));
            $refund->setType('Refund');
            $refund->setPurchase($purchase);
            $entityManager->persist($refund);
            $purchase->addPayment($refund);

            $purchase->setTotal(0.00);

            $purchase->updatePaymentStatus();

            $this->addFlash('success', 'Purchase PUR-' . str_pad($purchase->getId(), 4, '0', STR_PAD_LEFT) . ' has been successfully fully refunded and marked as Refunded.');
        } else {
            $purchase->setPaymentStatus('Cancelled');

            foreach ($purchase->getPurchaseItems() as $item) {
                if ($item->getStatus() === 'Active') {
                    $item->setStatus('Cancelled');
                    $entityManager->persist($item);
                    if ($item->getProduct()) {
                        $product = $item->getProduct();
                        $product->setStockQuantity($product->getStockQuantity() - $item->getQuantity());
                        $entityManager->persist($product);
                    }
                }
            }

            foreach ($purchase->getPayments() as $payment) {
                $entityManager->remove($payment);
            }

            $this->addFlash('success', 'Purchase invoice PUR-' . str_pad($purchase->getId(), 4, '0', STR_PAD_LEFT) . ' has been successfully cancelled and stock was updated.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
    }

    #[Route('/item/{id}/refund', name: 'app_purchases_item_refund', methods: ['POST'])]
    public function refundItem(PurchaseItem $item, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('edit.purchases')) {
            throw $this->createAccessDeniedException('You do not have permission to refund purchase items.');
        }
        $purchase = $item->getPurchase();
        if ($purchase->getPaymentStatus() === 'Cancelled') {
            $this->addFlash('error', 'Cannot refund items for a cancelled purchase.');
            return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
        }

        if ($item->getStatus() === 'Refunded') {
            $this->addFlash('warning', 'This item is already refunded.');
            return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
        }

        $item->setStatus('Refunded');
        $entityManager->persist($item);

        if ($item->getProduct()) {
            $product = $item->getProduct();
            $product->setStockQuantity($product->getStockQuantity() - $item->getQuantity());
            $entityManager->persist($product);
        }

        $newTotal = 0.00;
        foreach ($purchase->getPurchaseItems() as $purchaseItem) {
            if ($purchaseItem->getStatus() === 'Active') {
                $newTotal += (float) $purchaseItem->getPrice() * $purchaseItem->getQuantity();
            }
        }
        $purchase->setTotal($newTotal);

        $purchase->updatePaymentStatus();

        $entityManager->flush();

        $this->addFlash('success', 'Successfully cancelled & refunded the product: ' . $item->getPName() . '. The grand total was reduced, triggering an overpaid balance.');
        return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
    }
}
