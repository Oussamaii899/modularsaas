<?php

namespace App\Command;

use App\Entity\Payment;
use App\Entity\PurchaseItem;
use App\Entity\SaleItem;
use App\Repository\ProductRepository;
use App\Repository\PurchaseRepository;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-data',
    description: 'Populates IDs 1-3 for Sales and Purchases with items and payments',
)]
class PopulateDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleRepository $saleRepository,
        private PurchaseRepository $purchaseRepository,
        private ProductRepository $productRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $products = $this->productRepository->findAll();
        
        if (count($products) < 2) {
            $io->error('Not enough products found. Please create at least 2 products first.');
            return Command::FAILURE;
        }

        // --- SALES ---
        $sales = $this->saleRepository->findBy(['id' => [1, 2, 3]]);
        foreach ($sales as $sale) {
            $io->info("Processing Sale ID: " . $sale->getId());
            
            // 1. Ensure it has at least one item if empty (though research showed they have some)
            if ($sale->getSaleItems()->isEmpty()) {
                $item = new SaleItem();
                $item->setSale($sale);
                $item->setContact($sale->getContact());
                $item->setProduct($products[0]);
                $item->setPName($products[0]->getName());
                $item->setPSku($products[0]->getSku());
                $item->setQuantity(2);
                $item->setPrice($products[0]->getPrice());
                $sale->addSaleItem($item);
                $this->entityManager->persist($item);
            }

            // 2. Add Payments
            // Clear existing payments first to match requested state exactly
            foreach($sale->getPayments() as $p) $this->entityManager->remove($p);
            $this->entityManager->flush();

            if ($sale->getId() == 1 || $sale->getId() == 2) {
                // Full Payment
                $payment = new Payment();
                $payment->setAmount($sale->getTotal());
                $payment->setMethod('Bank Transfer');
                $payment->setReference('TXN-FULL-' . $sale->getId());
                $payment->setCreatedAt(new \DateTimeImmutable());
                $sale->addPayment($payment);
                $this->entityManager->persist($payment);
                $io->success("Sale ID " . $sale->getId() . " marked as PAID");
            } elseif ($sale->getId() == 3) {
                // Partial Payment ($50)
                $payment = new Payment();
                $payment->setAmount(50.00);
                $payment->setMethod('Cash');
                $payment->setReference('TXN-PARTIAL');
                $payment->setCreatedAt(new \DateTimeImmutable());
                $sale->addPayment($payment);
                $this->entityManager->persist($payment);
                $io->success("Sale ID 3 marked as PARTIAL ($50)");
            }
            
            $sale->updatePaymentStatus();
        }

        // --- PURCHASES ---
        $purchases = $this->purchaseRepository->findBy(['id' => [1, 2, 3]]);
        foreach ($purchases as $purchase) {
            $io->info("Processing Purchase ID: " . $purchase->getId());

            // 1. Add Items (Research found purchases were empty)
            if ($purchase->getPurchaseItems()->isEmpty()) {
                $item = new PurchaseItem();
                $item->setPurchase($purchase);
                $item->setContact($purchase->getContact());
                $p = ($purchase->getId() == 3) ? $products[1] : $products[0];
                $item->setProduct($p);
                $item->setPName($p->getName());
                $item->setPSku($p->getSku());
                $item->setQuantity(5);
                $item->setPrice($p->getPrice() * 0.8); // Discounted buy price
                $purchase->addPurchaseItem($item);
                $this->entityManager->persist($item);
                
                // Recalculate total for purchase manually since it might not be automated in entity
                $total = 0;
                foreach($purchase->getPurchaseItems() as $pi) {
                    $total += $pi->getQuantity() * $pi->getPrice();
                }
                $purchase->setTotal($total);
            }

            // 2. Add Payments
            foreach($purchase->getPayments() as $p) $this->entityManager->remove($p);
            $this->entityManager->flush();

            if ($purchase->getId() == 1 || $purchase->getId() == 2) {
                // Full Payment
                $payment = new Payment();
                $payment->setAmount($purchase->getTotal());
                $payment->setMethod('Wire Transfer');
                $payment->setReference('PURCH-PAY-' . $purchase->getId());
                $payment->setCreatedAt(new \DateTimeImmutable());
                $purchase->addPayment($payment);
                $this->entityManager->persist($payment);
                $io->success("Purchase ID " . $purchase->getId() . " marked as PAID");
            } else {
                $io->warning("Purchase ID 3 left UNPAID");
            }
            
            $purchase->updatePaymentStatus();
        }

        $this->entityManager->flush();

        $io->success('Data population complete!');

        return Command::SUCCESS;
    }
}
