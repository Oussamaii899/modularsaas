<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ClientType;
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

#[Route('/clients')]
#[IsGranted('ROLE_USER')]
final class ClientsController extends AbstractController
{
    #[Route(name: 'app_clients_index', methods: ['GET'])]
    public function index(Request $request, ContactRepository $contactRepository, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('see.sale.clients')) {
            throw $this->createAccessDeniedException('You do not have permission to view clients.');
        }
        $page = $request->query->getInt('page', 1);
        $query = $request->query->get('q');
        
        $data = $contactRepository->searchAndPaginate('client', $query, $page, 10);

        return $this->render('clients/index.html.twig', [
            'clients' => $data['items'],
            'pagesCount' => $data['pagesCount'],
            'currentPage' => $data['currentPage'],
            'totalItems' => $data['totalItems'],
            'searchQuery' => $query,
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => '#'],
                ['label' => 'Clients', 'url' => $this->generateUrl('app_clients_index')],
            ],
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
        ]);
    }

    #[Route('/new', name: 'app_clients_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('add.clients')) {
            throw $this->createAccessDeniedException('You do not have permission to add clients.');
        }
        $contact = new Contact();
        $contact->setType('client');
        $form = $this->createForm(ClientType::class, $contact);
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

            return $this->redirectToRoute('app_clients_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('clients/new.html.twig', [
            'client' => $contact,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => '#'],
                ['label' => 'Clients', 'url' => $this->generateUrl('app_clients_index')],
                ['label' => 'New Client', 'url' => $this->generateUrl('app_clients_new')],
            ],
        ]);
    }

    #[Route('/{slug}', name: 'app_clients_show', methods: ['GET'])]
    public function show(Contact $contact, SettingRepository $settingRepository, SaleRepository $saleRepository, PurchaseRepository $purchaseRepository): Response
    {
        if (!$this->isGranted('see.sale.clients')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        if ($contact->getType() !== 'client') {
            throw $this->createNotFoundException('Client not found');
        }

        $allSales = $saleRepository->findBy(['contact' => $contact]);
        $lifetimeRevenue = 0;
        $activeSalesCount = 0;
        foreach ($allSales as $sale) {
            if ($sale->getPaymentStatus() !== 'Cancelled' && $sale->getPaymentStatus() !== 'Refunded') {
                $lifetimeRevenue += $sale->getTotal();
                $activeSalesCount++;
            }
        }

        $recentSales = $saleRepository->findBy( ['contact' => $contact], ['created_at' => 'DESC'], 5 );

        $allPurchases = $purchaseRepository->findBy(['contact' => $contact]);
        $lifetimeSpending = 0;
        $activePurchasesCount = 0;
        foreach ($allPurchases as $purchase) {
            if ($purchase->getPaymentStatus() !== 'Cancelled' && $purchase->getPaymentStatus() !== 'Refunded') {
                $lifetimeSpending += $purchase->getTotal();
                $activePurchasesCount++;
            }
        }

        $recentPurchases = $purchaseRepository->findBy( ['contact' => $contact], ['created_at' => 'DESC'], 5 );

        return $this->render('clients/show.html.twig', [
            'client' => $contact,
            'recent_sales' => $recentSales,
            'recent_purchases' => $recentPurchases,
            'lifetime_revenue' => $lifetimeRevenue,
            'lifetime_spending' => $lifetimeSpending,
            'total_orders' => $activeSalesCount,
            'total_purchases' => $activePurchasesCount,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => '#'],
                ['label' => 'Clients', 'url' => $this->generateUrl('app_clients_index')],
                ['label' => $contact->getName(), 'url' => $this->generateUrl('app_clients_show', ['slug' => $contact->getSlug()])],
            ],
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_clients_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Contact $contact, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('edit.clients')) {
            throw $this->createAccessDeniedException('You do not have permission to edit clients.');
        }
        if ($contact->getType() !== 'client') {
            throw $this->createNotFoundException('Client not found');
        }

        $form = $this->createForm(ClientType::class, $contact);
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

            return $this->redirectToRoute('app_clients_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('clients/edit.html.twig', [
            'client' => $contact,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Sales', 'url' => '#'],
                ['label' => 'Clients', 'url' => $this->generateUrl('app_clients_index')],
                ['label' => 'Edit Profile', 'url' => $this->generateUrl('app_clients_edit', ['slug' => $contact->getSlug()])],
            ],
        ]);
    }

    #[Route('/{slug}', name: 'app_clients_delete', methods: ['POST'])]
    public function delete(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('delete.clients')) {
            throw $this->createAccessDeniedException('You do not have permission to delete clients.');
        }
        if ($contact->getType() !== 'client') {
            throw $this->createNotFoundException('Client not found');
        }

        if ($this->isCsrfTokenValid('delete'.$contact->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_clients_index', [], Response::HTTP_SEE_OTHER);
    }
}
