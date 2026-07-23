<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductScreen;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
use App\Repository\PurchaseItemRepository;
use App\Repository\SaleItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/products')]
#[IsGranted('ROLE_USER')]
class ProductsController extends AbstractController
{
    #[Route(name: 'app_products_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('see.products')) {
            throw $this->createAccessDeniedException('You do not have permission to view products.');
        }
        $page = $request->query->getInt('page', 1);
        $query = $request->query->get('q');
        
        $data = $productRepository->searchAndPaginate($query, $page, 10);

        return $this->render('products/index.html.twig', [
            'products' => $data['items'],
            'pagesCount' => $data['pagesCount'],
            'currentPage' => $data['currentPage'],
            'totalItems' => $data['totalItems'],
            'searchQuery' => $query,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Products', 'url' => $this->generateUrl('app_products_index')],
            ],
        ]);
    }

    #[Route('/new', name: 'app_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('add.products')) {
            throw $this->createAccessDeniedException('You do not have permission to add products.');
        }
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadsDir, $newFilename);
                $product->setImage($newFilename);
            }

            $screenFiles = $form->get('screenFiles')->getData();
            if ($screenFiles) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }
                foreach ($screenFiles as $screenFile) {
                    $newFilename = uniqid() . '.' . $screenFile->guessExtension();
                    $screenFile->move($uploadsDir, $newFilename);
                    
                    $productScreen = new ProductScreen();
                    $productScreen->setUrl($newFilename);
                    $product->addScreen($productScreen);
                    $entityManager->persist($productScreen);
                }
            }

            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_products_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('products/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Products', 'url' => $this->generateUrl('app_products_index')],
                ['label' => 'New Product', 'url' => $this->generateUrl('app_products_new')],
            ],
        ]);
    }

    #[Route('/{slug}', name: 'app_products_show', methods: ['GET'])]
    public function show(
        string $slug,
        ProductRepository $productRepository,
        SettingRepository $settingRepository,
        PurchaseItemRepository $purchaseItemRepository,
        SaleItemRepository $saleItemRepository
    ): Response {
        if (!$this->isGranted('see.products')) {
            throw $this->createAccessDeniedException('You do not have permission to view products.');
        }

        $product = $productRepository->findOneBy(['slug' => $slug]);
        if (!$product && ctype_digit($slug)) {
            $product = $productRepository->find((int) $slug);
        }

        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }
        $recentPurchases = $purchaseItemRepository->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->where('pi.product = :product')
            ->setParameter('product', $product)
            ->orderBy('p.created_at', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $recentSales = $saleItemRepository->createQueryBuilder('si')
            ->join('si.sale', 's')
            ->where('si.product = :product')
            ->setParameter('product', $product)
            ->orderBy('s.created_at', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $totalPurchasesData = $purchaseItemRepository->createQueryBuilder('pi')
            ->select('SUM(pi.quantity) as totalQty, SUM(pi.quantity * pi.price) as totalVal')
            ->where('pi.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();

        $totalSalesData = $saleItemRepository->createQueryBuilder('si')
            ->select('SUM(si.quantity) as totalQty, SUM(si.quantity * si.price) as totalVal')
            ->where('si.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();

        return $this->render('products/show.html.twig', [
            'product' => $product,
            'recent_purchases' => $recentPurchases,
            'recent_sales' => $recentSales,
            'total_purchases_qty' => $totalPurchasesData['totalQty'] ?? 0,
            'total_purchases_val' => $totalPurchasesData['totalVal'] ?? 0,
            'total_sales_qty' => $totalSalesData['totalQty'] ?? 0,
            'total_sales_val' => $totalSalesData['totalVal'] ?? 0,
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('app_dashboard')],
                ['label' => 'Products', 'url' => $this->generateUrl('app_products_index')],
                ['label' => $product->getName(), 'url' => $this->generateUrl('app_products_show', ['slug' => $product->getSlug()])],
            ],
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_products_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, SettingRepository $settingRepository): Response
    {
        if (!$this->isGranted('edit.products')) {
            throw $this->createAccessDeniedException('You do not have permission to edit products.');
        }
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            $clearImage = $request->request->get('clearImage') === '1';
            if ($imageFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }
                $oldImage = $product->getImage();
                if ($oldImage && file_exists($uploadsDir . '/' . $oldImage)) {
                    unlink($uploadsDir . '/' . $oldImage);
                }
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadsDir, $newFilename);
                $product->setImage($newFilename);
            } elseif ($clearImage) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                $oldImage = $product->getImage();
                if ($oldImage && file_exists($uploadsDir . '/' . $oldImage)) {
                    unlink($uploadsDir . '/' . $oldImage);
                }
                $product->setImage(null);
            }

            $screenFiles = $form->get('screenFiles')->getData();
            if ($screenFiles) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }
                foreach ($screenFiles as $screenFile) {
                    $newFilename = uniqid() . '.' . $screenFile->guessExtension();
                    $screenFile->move($uploadsDir, $newFilename);
                    
                    $productScreen = new ProductScreen();
                    $productScreen->setUrl($newFilename);
                    $product->addScreen($productScreen);
                    $entityManager->persist($productScreen);
                }
            }

            $deletedScreenIds = array_filter(
                array_unique(array_map('intval', explode(',', (string) $request->request->get('deletedScreens', '')))),
                static fn (int $id): bool => $id > 0
            );
            foreach ($deletedScreenIds as $screenId) {
                $productScreen = $entityManager->getRepository(ProductScreen::class)->find($screenId);
                if (!$productScreen || $productScreen->getProduct() !== $product) {
                    continue;
                }

                $filename = $productScreen->getUrl();
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if ($filename && file_exists($uploadsDir . '/' . $filename)) {
                    unlink($uploadsDir . '/' . $filename);
                }

                $entityManager->remove($productScreen);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_products_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('products/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'user' => $this->getUser(),
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
            'breadcrumbs' => [
                ['label' => 'Inventory', 'url' => '#'],
                ['label' => 'Products', 'url' => $this->generateUrl('app_products_index')],
                ['label' => 'Edit Product', 'url' => $this->generateUrl('app_products_edit', ['slug' => $product->getSlug()])],
            ],
        ]);
    }

    #[Route('/items/{id}/update-status', name: 'app_products_item_update_status', methods: ['POST'])]
    public function updateItemStatus(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isGranted('edit.products')) {
            throw $this->createAccessDeniedException('You do not have permission to edit product items.');
        }

        $item = $entityManager->getRepository(\App\Entity\ProductItem::class)->find($id);
        if (!$item) {
            $this->addFlash('error', 'Product item not found.');
            return $this->redirectToRoute('app_products_index');
        }

        $status = $request->request->get('status');
        $notes = $request->request->get('notes');

        if (in_array($status, [
            \App\Entity\ProductItem::STATUS_AVAILABLE,
            \App\Entity\ProductItem::STATUS_SOLD,
            \App\Entity\ProductItem::STATUS_REFUNDED_OK,
            \App\Entity\ProductItem::STATUS_REFUNDED_DEFECTIVE,
            \App\Entity\ProductItem::STATUS_DAMAGED,
            \App\Entity\ProductItem::STATUS_RESERVED
        ], true)) {
            $item->setStatus($status);
        }

        if ($notes !== null) {
            $item->setNotes(trim($notes));
        }

        $entityManager->flush();
        $this->addFlash('success', 'Product item item status updated.');

        return $this->redirectToRoute('app_products_show', ['slug' => $item->getProduct()->getSlug()]);
    }

    #[Route('/api/{id}/price', name: 'api_product_price', methods: ['GET'])]
    public function getPrice(Product $product): Response
    {
        if (!$this->isGranted('see.products')) {
            throw $this->createAccessDeniedException('You do not have permission to view products.');
        }
        return $this->json([
            'id' => $product->getId(),
            'price' => $product->getPrice(),
            'purchasePrice' => $product->getPurchasePrice() ?: $product->getPrice(),
            'isSerialized' => $product->isSerialized(),
        ]);
    }

    #[Route('/screen/{id}/delete', name: 'app_products_screen_delete', methods: ['DELETE'])]
    public function deleteScreen(int $id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('edit.products')) {
            throw $this->createAccessDeniedException('You do not have permission to delete product screenshots.');
        }
        $productScreen = $entityManager->getRepository(ProductScreen::class)->find($id);
        if (!$productScreen) {
            return $this->json(['error' => 'Screen not found'], Response::HTTP_NOT_FOUND);
        }

        $filename = $productScreen->getUrl();
        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        if ($filename && file_exists($uploadsDir . '/' . $filename)) {
            unlink($uploadsDir . '/' . $filename);
        }

        $entityManager->remove($productScreen);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{slug}', name: 'app_products_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('delete.products')) {
            throw $this->createAccessDeniedException('You do not have permission to delete products.');
        }
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
            $mainImage = $product->getImage();
            if ($mainImage && file_exists($uploadsDir . '/' . $mainImage)) {
                unlink($uploadsDir . '/' . $mainImage);
            }
            
            foreach ($product->getScreens() as $screen) {
                $filename = $screen->getUrl();
                if ($filename && file_exists($uploadsDir . '/' . $filename)) {
                    unlink($uploadsDir . '/' . $filename);
                }
            }

            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_products_index', [], Response::HTTP_SEE_OTHER);
    }
}
