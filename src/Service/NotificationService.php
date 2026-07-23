<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use App\Repository\PurchaseRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private const LOW_STOCK_THRESHOLD = 5;
    private const AI_COOLDOWN_DAYS = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private ProductRepository $productRepository,
        private SaleRepository $saleRepository,
        private PurchaseRepository $purchaseRepository,
        private SettingRepository $settingRepository,
    ) {}

    /**
     * Create a notification for a specific user (if no recent duplicate exists).
     */
    public function create(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $linkUrl = null,
        int $dedupWithinHours = 24
    ): ?Notification {
        // Dedup: skip if a similar notification was sent recently
        if ($dedupWithinHours > 0 && $this->notificationRepository->existsRecentForUser($user, $title, $dedupWithinHours)) {
            return null;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setLinkUrl($linkUrl);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Run all automatic checks and generate notifications for a user.
     * Call this on dashboard load or via a scheduled command.
     */
    public function runChecksForUser(User $user): void
    {
        $this->checkLowStock($user);
        $this->checkOutstandingBalances($user);
        $this->checkAiCooldown($user);
    }

    /**
     * Check products with low stock and notify.
     */
    private function checkLowStock(User $user): void
    {
        $lowStockProducts = $this->productRepository->findLowStockProducts(self::LOW_STOCK_THRESHOLD);

        foreach ($lowStockProducts as $product) {
            $title = "Low Stock: {$product->getName()}";
            $this->create(
                $user,
                $title,
                "Product \"{$product->getName()}\" has only {$product->getStockQuantity()} unit(s) remaining. Consider restocking soon.",
                'warning',
                '/products/' . $product->getSlug(),
                // Only re-notify once per 12 hours per product
                12
            );
        }
    }

    /**
     * Check outstanding (unpaid/partial) sales balances and notify.
     */
    private function checkOutstandingBalances(User $user): void
    {
        $unpaidSales = $this->saleRepository->findUnpaidOrPartial();

        if (count($unpaidSales) > 0) {
            $totalOutstanding = array_reduce($unpaidSales, fn($carry, $s) => $carry + $s->getBalance(), 0.0);
            $currency = $this->settingRepository->findOneBy(['keyName' => 'currency'])?->getValue() ?? '$';

            $this->create(
                $user,
                'Outstanding Receivables Alert',
                sprintf(
                    '%d invoice(s) have outstanding balances totalling %s%.2f. Follow up to improve cash flow.',
                    count($unpaidSales),
                    $currency,
                    $totalOutstanding
                ),
                'danger',
                '/sales/overview',
                // Re-notify at most once per day
                24
            );
        }

        // Outstanding purchase payables
        $unpaidPurchases = $this->purchaseRepository->findUnpaidOrPartial();
        if (count($unpaidPurchases) > 0) {
            $totalOwed = array_reduce($unpaidPurchases, fn($carry, $p) => $carry + $p->getBalance(), 0.0);
            $currency = $this->settingRepository->findOneBy(['keyName' => 'currency'])?->getValue() ?? '$';

            $this->create(
                $user,
                'Outstanding Payables Alert',
                sprintf(
                    '%d purchase(s) have outstanding supplier balances totalling %s%.2f.',
                    count($unpaidPurchases),
                    $currency,
                    $totalOwed
                ),
                'warning',
                '/purchases/overview',
                24
            );
        }
    }

    /**
     * Check if the AI CFO cooldown has expired and notify the user.
     */
    private function checkAiCooldown(User $user): void
    {
        $lastGenSetting = $this->settingRepository->findOneBy(['keyName' => 'ai_last_generated_at']);
        if (!$lastGenSetting || !$lastGenSetting->getValue()) {
            return;
        }

        try {
            $lastGeneratedAt = new \DateTime($lastGenSetting->getValue());
        } catch (\Exception) {
            return;
        }

        $now = new \DateTime();
        $diff = $now->diff($lastGeneratedAt);
        $daysDiff = (int) $diff->format('%a');

        if ($daysDiff >= self::AI_COOLDOWN_DAYS) {
            $this->create(
                $user,
                'CFO Insights Ready to Generate',
                'Your 3-day cooldown has expired. Head to Sales Overview to generate a new AI CFO Growth Report.',
                'success',
                '/sales/overview',
                // Only notify once per cooldown period (72h)
                72
            );
        }
    }
}
