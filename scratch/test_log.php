<?php
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$evm = $em->getEventManager();
$listeners = $evm->getListeners('onFlush');

echo "Registered listeners for onFlush:\n";
foreach ($listeners as $listener) {
    if (is_object($listener)) {
        echo "- " . get_class($listener) . "\n";
    } else {
        echo "- (Non-object listener)\n";
    }
}
