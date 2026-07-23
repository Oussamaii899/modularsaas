<?php

namespace App\Twig;

use App\Repository\SaleRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Repository\SettingRepository;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private SaleRepository $saleRepository,
        private \App\Repository\NoteRepository $noteRepository,
        private SettingRepository $settingRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_overpaid_sales_count', [$this, 'getOverpaidSalesCount']),
            new TwigFunction('get_notes', [$this, 'getNotes']),
        ];
    }

    public function getOverpaidSalesCount(): int
    {
        try {
            return $this->saleRepository->count(['paymentStatus' => 'Overpaid']);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getNotes(string $targetType, int $targetId): array
    {
        try {
            return $this->noteRepository->findBy(
                ['targetType' => $targetType, 'targetId' => $targetId],
                ['createdAt' => 'DESC']
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getGlobals(): array
    {
        try {
            $setting = $this->settingRepository->findOneBy(['keyName' => 'active_module']);
            $activeModule = $setting ? $setting->getValue() : 'none';
        } catch (\Exception $e) {
            $activeModule = 'none';
        }

        return [
            'active_module' => $activeModule,
        ];
    }
}
