<?php

namespace App\Twig;

use App\Repository\SaleRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private SaleRepository $saleRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_overpaid_sales_count', [$this, 'getOverpaidSalesCount']),
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
}
