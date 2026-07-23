<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\SupplierType;
use App\Repository\ContactRepository;
use App\Repository\PurchaseRepository;
use App\Repository\SaleRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/suppliers')]
#[IsGranted('ROLE_USER')]
final class SuppliersController extends AbstractController
{
    #[Route(name: 'app_suppliers_index', methods: ['GET'])]
    public function index(Request $request, ContactRepository $contactRepository, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('see.purchase.suppliers')) {
            throw $this->createAccessDeniedException('You do not have permission to view suppliers.');
        }
        $page = $request->query->getInt('page', 1);
        $query = $request->query->get('q');
        
        $data = $contactRepository->searchAndPaginate('supplier', $query, $page, 10);

        return $this->render('suppliers/index.html.twig', [
            'suppliers' => $data['items'],
            'pagesCount' => $data['pagesCount'],
            'currentPage' => $data['currentPage'],
            'totalItems' => $data['totalItems'],
            'searchQuery' => $query,
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => '#'],
                ['label' => 'Suppliers', 'url' => $this->generateUrl('app_suppliers_index')],
            ],
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
        ]);
    }

    #[Route('/new', name: 'app_suppliers_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('add.suppliers')) {
            throw $this->createAccessDeniedException('You do not have permission to add suppliers.');
        }
        $contact = new Contact();
        $contact->setType('supplier');
        $form = $this->createForm(SupplierType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }
                $newFilename = uniqid() . '.' . $avatarFile->guessExtension();
                $avatarFile->move($uploadsDir, $newFilename);
                $contact->setAvatar($newFilename);
            }
            $entityManager->persist($contact);
            $entityManager->flush();

            return $this->redirectToRoute('app_suppliers_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('suppliers/new.html.twig', [
            'supplier' => $contact,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => '#'],
                ['label' => 'Suppliers', 'url' => $this->generateUrl('app_suppliers_index')],
                ['label' => 'New Supplier', 'url' => $this->generateUrl('app_suppliers_new')],
            ],
        ]);
    }

    #[Route('/{slug}', name: 'app_suppliers_show', methods: ['GET'])]
    public function show(Contact $contact, SettingRepository $settingRepository, PurchaseRepository $purchaseRepository, SaleRepository $saleRepository): Response
    {
        if (!$this->isGranted('see.purchase.suppliers')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        if ($contact->getType() !== 'supplier') {
            throw $this->createNotFoundException('Supplier not found');
        }

        $allPurchases = $purchaseRepository->findBy(['contact' => $contact]);
        $lifetimeSpending = 0;
        $activePurchasesCount = 0;
        foreach ($allPurchases as $purchase) {
            if ($purchase->getPaymentStatus() !== 'Cancelled' && $purchase->getPaymentStatus() !== 'Refunded') {
                $lifetimeSpending += $purchase->getTotal();
                $activePurchasesCount++;
            }
        }

        $recentPurchases = $purchaseRepository->findBy(
            ['contact' => $contact],
            ['created_at' => 'DESC'],
            5
        );

        $allSales = $saleRepository->findBy(['contact' => $contact]);
        $lifetimeRevenue = 0;
        $activeSalesCount = 0;
        foreach ($allSales as $sale) {
            if ($sale->getPaymentStatus() !== 'Cancelled' && $sale->getPaymentStatus() !== 'Refunded') {
                $lifetimeRevenue += $sale->getTotal();
                $activeSalesCount++;
            }
        }

        $recentSales = $saleRepository->findBy(
            ['contact' => $contact],
            ['created_at' => 'DESC'],
            5
        );

        $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';

        return $this->render('suppliers/show.html.twig', [
            'supplier' => $contact,
            'recent_purchases' => $recentPurchases,
            'recent_sales' => $recentSales,
            'lifetime_spending' => $lifetimeSpending,
            'lifetime_revenue' => $lifetimeRevenue,
            'total_orders' => $activePurchasesCount,
            'total_sales' => $activeSalesCount,
            'active_module' => $activeModule,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => '#'],
                ['label' => 'Suppliers', 'url' => $this->generateUrl('app_suppliers_index')],
                ['label' => $contact->getName(), 'url' => $this->generateUrl('app_suppliers_show', ['slug' => $contact->getSlug()])],
            ],
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_suppliers_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Contact $contact, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('edit.suppliers')) {
            throw $this->createAccessDeniedException('You do not have permission to edit suppliers.');
        }
        if ($contact->getType() !== 'supplier') {
            throw $this->createNotFoundException('Supplier not found');
        }

        $form = $this->createForm(SupplierType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatarFile')->getData();
            $clearAvatar = $request->request->get('clearAvatar') === '1';
            if ($avatarFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }
                $oldAvatar = $contact->getAvatar();
                if ($oldAvatar && file_exists($uploadsDir . '/' . $oldAvatar)) {
                    unlink($uploadsDir . '/' . $oldAvatar);
                }
                $newFilename = uniqid() . '.' . $avatarFile->guessExtension();
                $avatarFile->move($uploadsDir, $newFilename);
                $contact->setAvatar($newFilename);
            } elseif ($clearAvatar) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                $oldAvatar = $contact->getAvatar();
                if ($oldAvatar && file_exists($uploadsDir . '/' . $oldAvatar)) {
                    unlink($uploadsDir . '/' . $oldAvatar);
                }
                $contact->setAvatar(null);
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_suppliers_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('suppliers/edit.html.twig', [
            'supplier' => $contact,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Purchases', 'url' => '#'],
                ['label' => 'Suppliers', 'url' => $this->generateUrl('app_suppliers_index')],
                ['label' => 'Edit Profile', 'url' => $this->generateUrl('app_suppliers_edit', ['slug' => $contact->getSlug()])],
            ],
        ]);
    }

    #[Route('/{slug}', name: 'app_suppliers_delete', methods: ['POST'])]
    public function delete(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('delete.suppliers')) {
            throw $this->createAccessDeniedException('You do not have permission to delete suppliers.');
        }
        if ($contact->getType() !== 'supplier') {
            throw $this->createNotFoundException('Supplier not found');
        }

        if ($this->isCsrfTokenValid('delete'.$contact->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_suppliers_index', [], Response::HTTP_SEE_OTHER);
    }
}
