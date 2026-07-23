<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Product;
use App\Entity\Purchase;
use App\Entity\Sale;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        $cat = trim($request->query->get('cat', ''));

        if ($q === '') {
            return $this->json([]);
        }

        $results = [];

        // Determine which categories to search
        $categoriesToSearch = [];
        if ($cat) {
            $categoriesToSearch[] = $cat;
        } else {
            $categoriesToSearch = ['sale', 'product', 'contact', 'user', 'purchase'];
        }

        foreach ($categoriesToSearch as $category) {
            switch ($category) {
                case 'sale':
                    if ($this->isGranted('see.sales')) {
                        $sales = $entityManager->getRepository(Sale::class)->createQueryBuilder('s')
                            ->leftJoin('s.contact', 'c')
                            ->where('s.slug LIKE :q OR c.name LIKE :q')
                            ->setParameter('q', '%' . $q . '%')
                            ->setMaxResults(10)
                            ->getQuery()
                            ->getResult();

                        foreach ($sales as $sale) {
                            $results[] = [
                                'title' => sprintf('Sale: %s - %s ($%s)', $sale->getSlug(), $sale->getContact() ? $sale->getContact()->getName() : 'Unknown', $sale->getTotal()),
                                'url' => '/sales/' . $sale->getSlug(),
                                'category' => 'sale',
                            ];
                        }
                    }
                    break;

                case 'product':
                    if ($this->isGranted('see.products')) {
                        $products = $entityManager->getRepository(Product::class)->createQueryBuilder('p')
                            ->where('p.name LIKE :q OR p.sku LIKE :q OR p.description LIKE :q')
                            ->setParameter('q', '%' . $q . '%')
                            ->setMaxResults(10)
                            ->getQuery()
                            ->getResult();

                        foreach ($products as $product) {
                            $results[] = [
                                'title' => sprintf('Product: %s (Stock: %d)', $product->getName(), $product->getStockQuantity()),
                                'url' => '/products/' . $product->getSlug(),
                                'category' => 'product',
                            ];
                        }
                    }
                    break;

                case 'contact':
                    if ($this->isGranted('see.sales') || $this->isGranted('see.purchases')) {
                        $contacts = $entityManager->getRepository(Contact::class)->createQueryBuilder('c')
                            ->where('c.name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q')
                            ->setParameter('q', '%' . $q . '%')
                            ->setMaxResults(10)
                            ->getQuery()
                            ->getResult();

                        foreach ($contacts as $contact) {
                            $type = $contact->getType();
                            $url = ($type === 'supplier') ? '/suppliers/' . $contact->getSlug() : '/clients/' . $contact->getSlug();
                            $results[] = [
                                'title' => sprintf('Contact: %s (%s)', $contact->getName(), ucfirst($type ?? 'client')),
                                'url' => $url,
                                'category' => 'contact',
                            ];
                        }
                    }
                    break;

                case 'user':
                    if ($this->isGranted('ROLE_ADMIN')) {
                        $users = $entityManager->getRepository(User::class)->createQueryBuilder('u')
                            ->where('u.firstname LIKE :q OR u.lastname LIKE :q OR u.email LIKE :q OR u.username LIKE :q')
                            ->setParameter('q', '%' . $q . '%')
                            ->setMaxResults(10)
                            ->getQuery()
                            ->getResult();

                        foreach ($users as $user) {
                            $results[] = [
                                'title' => sprintf('User: %s %s (%s)', $user->getFirstname(), $user->getLastname(), $user->getUsername()),
                                'url' => '/users/' . $user->getId() . '/edit',
                                'category' => 'user',
                            ];
                        }
                    }
                    break;

                case 'purchase':
                    if ($this->isGranted('see.purchases')) {
                        $purchases = $entityManager->getRepository(Purchase::class)->createQueryBuilder('p')
                            ->leftJoin('p.contact', 'c')
                            ->where('p.slug LIKE :q OR c.name LIKE :q')
                            ->setParameter('q', '%' . $q . '%')
                            ->setMaxResults(10)
                            ->getQuery()
                            ->getResult();

                        foreach ($purchases as $purchase) {
                            $results[] = [
                                'title' => sprintf('Purchase: %s - %s ($%s)', $purchase->getSlug(), $purchase->getContact() ? $purchase->getContact()->getName() : 'Unknown', $purchase->getTotal()),
                                'url' => '/purchases/' . $purchase->getSlug(),
                                'category' => 'purchase',
                            ];
                        }
                    }
                    break;
            }
        }

        return $this->json($results);
    }
}
