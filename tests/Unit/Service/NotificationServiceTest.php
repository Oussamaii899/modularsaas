<?php

namespace App\Tests\Unit\Service;

use App\Entity\Notification;
use App\Entity\Product;
use App\Entity\Purchase;
use App\Entity\Sale;
use App\Entity\Setting;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\ProductRepository;
use App\Repository\PurchaseRepository;
use App\Repository\SaleRepository;
use App\Repository\SettingRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationService.
 *
 * All dependencies are PHPUnit mocks — no database required.
 *
 * Covers:
 *  - create(): happy path, dedup skip, dedup bypass when hours=0
 *  - checkLowStock(): notification per low-stock product
 *  - checkOutstandingBalances(): alerts for unpaid sales and purchases
 *  - checkAiCooldown(): skip when no setting, skip when not expired, notify when expired
 */
class NotificationServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var NotificationRepository&MockObject */
    private NotificationRepository $notificationRepo;

    /** @var ProductRepository&MockObject */
    private ProductRepository $productRepo;

    /** @var SaleRepository&MockObject */
    private SaleRepository $saleRepo;

    /** @var PurchaseRepository&MockObject */
    private PurchaseRepository $purchaseRepo;

    /** @var SettingRepository&MockObject */
    private SettingRepository $settingRepo;

    private NotificationService $service;

    protected function setUp(): void
    {
        $this->em               = $this->createMock(EntityManagerInterface::class);
        $this->notificationRepo = $this->createMock(NotificationRepository::class);
        $this->productRepo      = $this->createMock(ProductRepository::class);
        $this->saleRepo         = $this->createMock(SaleRepository::class);
        $this->purchaseRepo     = $this->createMock(PurchaseRepository::class);
        $this->settingRepo      = $this->createMock(SettingRepository::class);

        $this->service = new NotificationService(
            $this->em,
            $this->notificationRepo,
            $this->productRepo,
            $this->saleRepo,
            $this->purchaseRepo,
            $this->settingRepo,
        );
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function testCreateReturnsNotificationWhenNoDuplicateExists(): void
    {
        $user = $this->makeUser();

        $this->notificationRepo
            ->method('existsRecentForUser')
            ->willReturn(false);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->create($user, 'Test Title', 'Test message', 'info', null, 24);

        $this->assertInstanceOf(Notification::class, $result);
        $this->assertSame('Test Title', $result->getTitle());
        $this->assertSame('Test message', $result->getMessage());
        $this->assertSame('info', $result->getType());
    }

    public function testCreateReturnsNullWhenDuplicateExistsWithinWindow(): void
    {
        $user = $this->makeUser();

        $this->notificationRepo
            ->method('existsRecentForUser')
            ->willReturn(true);

        // Must NOT persist or flush when dedup hits
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->create($user, 'Duplicate', 'Already sent', 'warning', null, 24);

        $this->assertNull($result);
    }

    public function testCreateSkipsDeduplicationWhenDedupHoursIsZero(): void
    {
        $user = $this->makeUser();

        // existsRecentForUser must NOT be called when dedupWithinHours === 0
        $this->notificationRepo
            ->expects($this->never())
            ->method('existsRecentForUser');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->create($user, 'Immediate', 'No dedup', 'info', null, 0);

        $this->assertInstanceOf(Notification::class, $result);
    }

    public function testCreateSetsLinkUrl(): void
    {
        $user = $this->makeUser();

        $this->notificationRepo->method('existsRecentForUser')->willReturn(false);
        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->create($user, 'Title', 'Msg', 'success', '/some/path', 0);

        $this->assertNotNull($result);
        $this->assertSame('/some/path', $result->getLinkUrl());
    }

    // =========================================================================
    // checkLowStock() — called via runChecksForUser()
    // =========================================================================

    public function testCheckLowStockCreatesOneNotificationPerProduct(): void
    {
        $user = $this->makeUser();

        $p1 = $this->makeProduct('Aspirin', 'aspirin', 2);
        $p2 = $this->makeProduct('Ibuprofen', 'ibuprofen', 4);

        $this->productRepo
            ->method('findLowStockProducts')
            ->willReturn([$p1, $p2]);

        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->settingRepo->method('findOneBy')->willReturn(null);

        // No duplicates — will persist for each product
        $this->notificationRepo->method('existsRecentForUser')->willReturn(false);

        // Two products → 2 persist + 2 flush calls
        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->exactly(2))->method('flush');

        $this->service->runChecksForUser($user);
    }

    public function testCheckLowStockSkipsProductsWhenDuplicateExists(): void
    {
        $user = $this->makeUser();

        $p1 = $this->makeProduct('Aspirin', 'aspirin', 2);

        $this->productRepo
            ->method('findLowStockProducts')
            ->willReturn([$p1]);

        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->settingRepo->method('findOneBy')->willReturn(null);

        // Mark as duplicate → no persist
        $this->notificationRepo->method('existsRecentForUser')->willReturn(true);

        $this->em->expects($this->never())->method('persist');

        $this->service->runChecksForUser($user);
    }

    public function testCheckLowStockDoesNothingWhenNoLowStockProducts(): void
    {
        $user = $this->makeUser();

        $this->productRepo
            ->method('findLowStockProducts')
            ->willReturn([]);

        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->settingRepo->method('findOneBy')->willReturn(null);

        $this->em->expects($this->never())->method('persist');

        $this->service->runChecksForUser($user);
    }

    // =========================================================================
    // checkOutstandingBalances() — called via runChecksForUser()
    // =========================================================================

    public function testCheckOutstandingBalancesNotifiesForUnpaidSales(): void
    {
        $user = $this->makeUser();

        // One unpaid sale with a $200 balance
        $sale = $this->makeUnpaidSale(200.0);

        $this->productRepo->method('findLowStockProducts')->willReturn([]);
        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([$sale]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);

        // Currency setting (dollar sign)
        $currencySetting = (new Setting())->setKeyName('currency')->setValue('$');
        $this->settingRepo
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($currencySetting) {
                if (($criteria['keyName'] ?? null) === 'currency') {
                    return $currencySetting;
                }
                return null;
            });

        $this->notificationRepo->method('existsRecentForUser')->willReturn(false);

        // 1 notification for sales
        $this->em->expects($this->once())->method('persist')->with(
            $this->callback(function (Notification $n) {
                return str_contains($n->getTitle(), 'Outstanding Receivables')
                    && $n->getType() === 'danger';
            })
        );
        $this->em->expects($this->once())->method('flush');

        $this->service->runChecksForUser($user);
    }

    public function testCheckOutstandingBalancesNotifiesForUnpaidPurchases(): void
    {
        $user = $this->makeUser();

        $purchase = $this->makeUnpaidPurchase(300.0);

        $this->productRepo->method('findLowStockProducts')->willReturn([]);
        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([$purchase]);

        $this->settingRepo->method('findOneBy')->willReturn(null);
        $this->notificationRepo->method('existsRecentForUser')->willReturn(false);

        $this->em->expects($this->once())->method('persist')->with(
            $this->callback(function (Notification $n) {
                return str_contains($n->getTitle(), 'Outstanding Payables')
                    && $n->getType() === 'warning';
            })
        );
        $this->em->expects($this->once())->method('flush');

        $this->service->runChecksForUser($user);
    }

    public function testCheckOutstandingBalancesDoesNothingWhenAllPaid(): void
    {
        $user = $this->makeUser();

        $this->productRepo->method('findLowStockProducts')->willReturn([]);
        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->settingRepo->method('findOneBy')->willReturn(null);

        $this->em->expects($this->never())->method('persist');

        $this->service->runChecksForUser($user);
    }

    // =========================================================================
    // checkAiCooldown() — called via runChecksForUser()
    // =========================================================================

    public function testCheckAiCooldownSkipsWhenSettingDoesNotExist(): void
    {
        $user = $this->makeUser();

        $this->productRepo->method('findLowStockProducts')->willReturn([]);
        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);

        // All findOneBy calls return null → no 'ai_last_generated_at' setting
        $this->settingRepo->method('findOneBy')->willReturn(null);

        // No AI notification should be sent
        $this->em->expects($this->never())->method('persist');

        $this->service->runChecksForUser($user);
    }

    public function testCheckAiCooldownSkipsWhenCooldownNotExpired(): void
    {
        $user = $this->makeUser();

        $this->productRepo->method('findLowStockProducts')->willReturn([]);
        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);

        // AI was generated 1 day ago — cooldown is 3 days, so no notification
        $lastGeneratedAt = (new \DateTime())->modify('-1 day')->format('Y-m-d H:i:s');
        $setting = (new Setting())->setKeyName('ai_last_generated_at')->setValue($lastGeneratedAt);

        $this->settingRepo
            ->method('findOneBy')
            ->willReturn($setting);

        $this->em->expects($this->never())->method('persist');

        $this->service->runChecksForUser($user);
    }

    public function testCheckAiCooldownNotifiesWhenCooldownHasExpired(): void
    {
        $user = $this->makeUser();

        $this->productRepo->method('findLowStockProducts')->willReturn([]);
        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);

        // AI was generated 4 days ago — cooldown of 3 days has expired
        $lastGeneratedAt = (new \DateTime())->modify('-4 days')->format('Y-m-d H:i:s');
        $setting = (new Setting())->setKeyName('ai_last_generated_at')->setValue($lastGeneratedAt);

        $this->settingRepo
            ->method('findOneBy')
            ->willReturn($setting);

        $this->notificationRepo->method('existsRecentForUser')->willReturn(false);

        // One AI cooldown notification expected
        $this->em->expects($this->once())->method('persist')->with(
            $this->callback(function (Notification $n) {
                return str_contains($n->getTitle(), 'CFO Insights')
                    && $n->getType() === 'success';
            })
        );
        $this->em->expects($this->once())->method('flush');

        $this->service->runChecksForUser($user);
    }

    public function testCheckAiCooldownSkipsWhenNotificationAlreadySent(): void
    {
        $user = $this->makeUser();

        $this->productRepo->method('findLowStockProducts')->willReturn([]);
        $this->saleRepo->method('findUnpaidOrPartial')->willReturn([]);
        $this->purchaseRepo->method('findUnpaidOrPartial')->willReturn([]);

        $lastGeneratedAt = (new \DateTime())->modify('-5 days')->format('Y-m-d H:i:s');
        $setting = (new Setting())->setKeyName('ai_last_generated_at')->setValue($lastGeneratedAt);

        $this->settingRepo->method('findOneBy')->willReturn($setting);

        // Dedup says it was already sent within the 72h window
        $this->notificationRepo->method('existsRecentForUser')->willReturn(true);

        $this->em->expects($this->never())->method('persist');

        $this->service->runChecksForUser($user);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setFirstname('Test');
        $user->setLastname('User');
        $user->setEmail('test@example.com');
        return $user;
    }

    private function makeProduct(string $name, string $slug, int $stock): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSlug($slug);
        $product->setStockQuantity($stock);
        $product->setPrice('10.00');
        return $product;
    }

    /**
     * Build a Sale mock with a getBalance() that returns the specified amount.
     */
    private function makeUnpaidSale(float $balance): object
    {
        $sale = $this->createMock(Sale::class);
        $sale->method('getBalance')->willReturn($balance);
        return $sale;
    }

    /**
     * Build a Purchase mock with a getBalance() that returns the specified amount.
     */
    private function makeUnpaidPurchase(float $balance): object
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getBalance')->willReturn($balance);
        return $purchase;
    }
}
