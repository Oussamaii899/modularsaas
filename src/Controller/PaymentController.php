<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Sale;
use App\Entity\Purchase;
use App\Form\PaymentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payments')]
class PaymentController extends AbstractController
{
    #[Route('/sale/{id}/record', name: 'app_payment_sale_record', methods: ['POST'])]
    public function recordForSale(Request $request, Sale $sale, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('add.sales')) {
            throw $this->createAccessDeniedException('You do not have permission to record sale payments.');
        }
        if ($sale->getPaymentStatus() === 'Cancelled') {
            $this->addFlash('error', 'Cannot record payments for a cancelled sale.');
            return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
        }

        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($payment->getAmount() <= 0) {
                $this->addFlash('error', 'Payment amount must be a positive number.');
                return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
            }

            $payment->setSale($sale);
            $entityManager->persist($payment);
            
            $sale->addPayment($payment);
            $sale->updatePaymentStatus();
            
            $entityManager->flush();

            $amount = (float) $payment->getAmount();
            $this->addFlash('success', 'Payment of $' . number_format($amount, 2) . ' recorded for INV-' . str_pad($sale->getId(), 4, '0', STR_PAD_LEFT));
        }

        return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
    }

    #[Route('/purchase/{id}/record', name: 'app_payment_purchase_record', methods: ['POST'])]
    public function recordForPurchase(Request $request, Purchase $purchase, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('add.purchases')) {
            throw $this->createAccessDeniedException('You do not have permission to record purchase payments.');
        }
        if ($purchase->getPaymentStatus() === 'Cancelled') {
            $this->addFlash('error', 'Cannot record payments for a cancelled purchase.');
            return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
        }

        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($payment->getAmount() <= 0) {
                $this->addFlash('error', 'Payment amount must be a positive number.');
                return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
            }

            $payment->setPurchase($purchase);
            $entityManager->persist($payment);
            
            $purchase->addPayment($payment);
            $purchase->updatePaymentStatus();
            
            $entityManager->flush();

            $amount = (float) $payment->getAmount();
            $this->addFlash('success', 'Payment of $' . number_format($amount, 2) . ' recorded for PUR-' . str_pad($purchase->getId(), 4, '0', STR_PAD_LEFT));
        }

        return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
    }

    #[Route('/sale/{id}/refund-overpayment', name: 'app_payment_sale_refund_overpayment', methods: ['POST'])]
    public function refundOverpaymentForSale(Sale $sale, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('edit.sales')) {
            throw $this->createAccessDeniedException('You do not have permission to refund sale overpayments.');
        }
        if ($sale->getPaymentStatus() !== 'Overpaid') {
            $this->addFlash('warning', 'This sale is not overpaid.');
            return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
        }

        $overpaidAmount = $sale->getPaidAmount() - (float) $sale->getTotal();
        if ($overpaidAmount <= 0) {
            return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
        }

        $payment = new Payment();
        $payment->setAmount(-$overpaidAmount);
        $payment->setMethod('Cash');
        $payment->setReference('Overpayment Refund');
        $payment->setType('Refund');
        $payment->setSale($sale);
        $entityManager->persist($payment);

        $sale->addPayment($payment);
        $sale->updatePaymentStatus();

        $entityManager->flush();

        $this->addFlash('success', 'Successfully refunded overpaid balance of $' . number_format($overpaidAmount, 2) . '. The invoice status is now Paid.');
        return $this->redirectToRoute('app_sales_show', ['slug' => $sale->getSlug()]);
    }

    #[Route('/purchase/{id}/refund-overpayment', name: 'app_payment_purchase_refund_overpayment', methods: ['POST'])]
    public function refundOverpaymentForPurchase(Purchase $purchase, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('edit.purchases')) {
            throw $this->createAccessDeniedException('You do not have permission to refund purchase overpayments.');
        }
        if ($purchase->getPaymentStatus() !== 'Overpaid') {
            $this->addFlash('warning', 'This purchase is not overpaid.');
            return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
        }

        $overpaidAmount = $purchase->getPaidAmount() - (float) $purchase->getTotal();
        if ($overpaidAmount <= 0) {
            return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
        }

        $payment = new Payment();
        $payment->setAmount(-$overpaidAmount);
        $payment->setMethod('Cash');
        $payment->setReference('Overpayment Refund');
        $payment->setType('Refund');
        $payment->setPurchase($purchase);
        $entityManager->persist($payment);

        $purchase->addPayment($payment);
        $purchase->updatePaymentStatus();

        $entityManager->flush();

        $this->addFlash('success', 'Successfully refunded overpaid balance of $' . number_format($overpaidAmount, 2) . '. The purchase status is now Paid.');
        return $this->redirectToRoute('app_purchases_show', ['slug' => $purchase->getSlug()]);
    }
}
