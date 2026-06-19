<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();
$saleRepository = $container->get('App\Repository\SaleRepository');
$purchaseRepository = $container->get('App\Repository\PurchaseRepository');

$date = '-30 days';
echo "Total Sales: " . var_export($saleRepository->totalByDate($date), true) . "\n";
echo "Total Purchases: " . var_export($purchaseRepository->totalByDate($date), true) . "\n";

$sales = $saleRepository->salesByDate($date);
echo "Sales Count: " . count($sales) . "\n";
if (!empty($sales)) {
    echo "First Sale created_at type: " . get_class($sales[0]['created_at']) . "\n";
}
