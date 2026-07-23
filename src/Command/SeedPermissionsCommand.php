<?php

namespace App\Command;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-permissions',
    description: 'Seeds standard permission slugs into the database',
)]
class SeedPermissionsCommand extends Command
{
    private const PERMISSIONS = [
        'see.dashboard',
        'see.notifications',
        'see.purchases',
        'see.sales',
        'see.products',
        'see.logs',
        
        'see.purchase.overview',
        'see.purchase.suppliers',
        'see.purchase.list',
        'see.sale.overview',
        'see.sale.clients',
        'see.sale.list',
        
        'add.suppliers',
        'edit.suppliers',
        'delete.suppliers',
        
        'add.purchases',
        'edit.purchases',
        'delete.purchases',
        
        'add.clients',
        'edit.clients',
        'delete.clients',
        
        'add.sales',
        'edit.sales',
        'delete.sales',
        
        'add.products',
        'edit.products',
        'delete.products',

        'perm.general',
        'perm.appearance',
        'perm.branding',
        'perm.maintenance',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PermissionRepository $permissionRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding Application Permissions');

        $count = 0;
        foreach (self::PERMISSIONS as $slug) {
            $existing = $this->permissionRepository->findOneBy(['name' => $slug]);
            if (!$existing) {
                $permission = new Permission();
                $permission->setName($slug);
                $this->entityManager->persist($permission);
                $io->writeln(sprintf('Creating permission: <info>%s</info>', $slug));
                $count++;
            } else {
                $io->writeln(sprintf('Permission already exists: <comment>%s</comment>', $slug));
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Successfully seeded %d new permissions!', $count));
        } else {
            $io->success('All permissions are already seeded.');
        }

        return Command::SUCCESS;
    }
}
