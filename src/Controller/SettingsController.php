<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use App\Repository\SaleRepository;
use App\Repository\PurchaseRepository;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/settings')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SettingRepository $settingRepository,
        EntityManagerInterface $entityManager,
        KernelInterface $kernel
    ): Response {
        // Must have at least one permission to view settings
        if (!$this->isGranted('ROLE_ADMIN') && 
            !$this->isGranted('perm.general') && 
            !$this->isGranted('perm.appearance') && 
            !$this->isGranted('perm.branding') && 
            !$this->isGranted('perm.maintenance')) {
            throw $this->createAccessDeniedException('You do not have permission to access settings.');
        }

        $generalKeys = ['business_name', 'address', 'phone', 'email', 'website', 'currency', 'timezone'];
        $appearanceKeys = ['theme', 'primary_color', 'sidebar_style'];
        $brandingKeys = ['company_name', 'invoice_prefix', 'invoice_footer'];
        $maintenanceKeys = ['maintenance_enabled'];

        $allKeys = array_merge($generalKeys, $appearanceKeys, $brandingKeys, $maintenanceKeys);

        if ($request->isMethod('POST')) {
            $hasChanges = false;

            // 1. General Settings update check
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('perm.general')) {
                foreach ($generalKeys as $key) {
                    if ($request->request->has($key)) {
                        $setting = $settingRepository->findOneBy(['keyName' => $key]);
                        if (!$setting) {
                            $setting = new Setting();
                            $setting->setKeyName($key);
                            $entityManager->persist($setting);
                        }
                        $setting->setValue($request->request->get($key));
                        $hasChanges = true;
                    }
                }

                // Handle business logo upload
                $uploadsDir = $kernel->getProjectDir() . '/public/uploads/branding';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                $file = $request->files->get('business_logo');
                if ($file) {
                    $newFilename = 'business_logo-' . uniqid() . '.' . $file->guessExtension();
                    try {
                        $oldSetting = $settingRepository->findOneBy(['keyName' => 'business_logo']);
                        if ($oldSetting && $oldSetting->getValue()) {
                            $oldFilePath = $uploadsDir . '/' . $oldSetting->getValue();
                            if (file_exists($oldFilePath)) {
                                @unlink($oldFilePath);
                            }
                        }
                        $file->move($uploadsDir, $newFilename);
                        if (!$oldSetting) {
                            $oldSetting = new Setting();
                            $oldSetting->setKeyName('business_logo');
                            $entityManager->persist($oldSetting);
                        }
                        $oldSetting->setValue($newFilename);
                        $hasChanges = true;
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload business logo');
                    }
                }
            }

            // 2. Appearance Settings update check
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('perm.appearance')) {
                foreach ($appearanceKeys as $key) {
                    if ($request->request->has($key)) {
                        $setting = $settingRepository->findOneBy(['keyName' => $key]);
                        if (!$setting) {
                            $setting = new Setting();
                            $setting->setKeyName($key);
                            $entityManager->persist($setting);
                        }
                        $setting->setValue($request->request->get($key));
                        $hasChanges = true;
                    }
                }
            }

            // 3. Branding Settings update check
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('perm.branding')) {
                foreach ($brandingKeys as $key) {
                    if ($request->request->has($key)) {
                        $setting = $settingRepository->findOneBy(['keyName' => $key]);
                        if (!$setting) {
                            $setting = new Setting();
                            $setting->setKeyName($key);
                            $entityManager->persist($setting);
                        }
                        $setting->setValue($request->request->get($key));
                        $hasChanges = true;
                    }
                }

                // Handle company logo upload
                $uploadsDir = $kernel->getProjectDir() . '/public/uploads/branding';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                $file = $request->files->get('company_logo');
                if ($file) {
                    $newFilename = 'company_logo-' . uniqid() . '.' . $file->guessExtension();
                    try {
                        $oldSetting = $settingRepository->findOneBy(['keyName' => 'company_logo']);
                        if ($oldSetting && $oldSetting->getValue()) {
                            $oldFilePath = $uploadsDir . '/' . $oldSetting->getValue();
                            if (file_exists($oldFilePath)) {
                                @unlink($oldFilePath);
                            }
                        }
                        $file->move($uploadsDir, $newFilename);
                        if (!$oldSetting) {
                            $oldSetting = new Setting();
                            $oldSetting->setKeyName('company_logo');
                            $entityManager->persist($oldSetting);
                        }
                        $oldSetting->setValue($newFilename);
                        $hasChanges = true;
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload company logo');
                    }
                }
            }

            // 4. Maintenance Settings update check (maintenance mode toggle inside main form POST)
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('perm.maintenance')) {
                foreach ($maintenanceKeys as $key) {
                    if ($request->request->has($key)) {
                        $setting = $settingRepository->findOneBy(['keyName' => $key]);
                        if (!$setting) {
                            $setting = new Setting();
                            $setting->setKeyName($key);
                            $entityManager->persist($setting);
                        }
                        $setting->setValue($request->request->get($key));
                        $hasChanges = true;
                    }
                }
            }

            if ($hasChanges) {
                $entityManager->flush();
                $this->addFlash('success', 'Settings updated successfully.');
            }
            return $this->redirectToRoute('app_settings');
        }

        // Get current values
        $settings = [];
        foreach (array_merge($allKeys, ['business_logo', 'company_logo']) as $key) {
            $settings[$key] = $settingRepository->findOneBy(['keyName' => $key])?->getValue();
        }

        return $this->render('settings/index.html.twig', [
            'settings' => $settings,
            'company_logo' => $settings['company_logo'],
            'company_name' => $settings['company_name'] ?? 'ModularSaaS',
        ]);
    }

    #[Route('/maintenance/backup-create', name: 'app_settings_backup_create', methods: ['POST'])]
    public function backupCreate(EntityManagerInterface $entityManager, KernelInterface $kernel): Response
    {
        $this->denyAccessUnlessGranted('perm.maintenance');
        try {
            $conn = $entityManager->getConnection();
            $schemaManager = $conn->createSchemaManager();
            $sql = "-- ModularSaaS Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            foreach ($schemaManager->listTables() as $table) {
                $tableName = $table->getName();
                $sql .= "DROP TABLE IF EXISTS `$tableName`;\n";

                $createSqls = $schemaManager->getDatabasePlatform()->getCreateTableSQL($table);
                foreach ($createSqls as $createSql) {
                    $sql .= $createSql . ";\n";
                }

                $rows = $conn->fetchAllAssociative("SELECT * FROM `$tableName`");
                foreach ($rows as $row) {
                    $cols = array_keys($row);
                    $vals = array_map(function($v) use ($conn) {
                        if ($v === null) return 'NULL';
                        return $conn->quote($v);
                    }, array_values($row));
                    $sql .= "INSERT INTO `$tableName` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
                }
                $sql .= "\n";
            }

            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            $backupDir = $kernel->getProjectDir() . '/var/backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0777, true);
            }

            $filename = 'backup-' . date('Y-m-d-H-i-s') . '.sql';
            $filepath = $backupDir . '/' . $filename;
            file_put_contents($filepath, $sql);

            // Allow direct download
            $response = new Response($sql);
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            );
            $response->headers->set('Content-Type', 'application/sql');
            $response->headers->set('Content-Disposition', $disposition);
            return $response;

        } catch (\Throwable $e) {
            $this->addFlash('error', 'Backup failed: ' . $e->getMessage());
            return $this->redirectToRoute('app_settings');
        }
    }

    #[Route('/maintenance/backup-restore', name: 'app_settings_backup_restore', methods: ['POST'])]
    public function backupRestore(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('perm.maintenance');
        $file = $request->files->get('backup_file');
        if (!$file) {
            $this->addFlash('error', 'Please upload a SQL backup file.');
            return $this->redirectToRoute('app_settings');
        }

        try {
            $sqlContent = file_get_contents($file->getPathname());
            $conn = $entityManager->getConnection();
            
            // Clean/split file content by semicolons followed by newline
            $queries = preg_split('/;\r?\n/', $sqlContent);
            
            $conn->beginTransaction();
            foreach ($queries as $query) {
                $trimmed = trim($query);
                if (!empty($trimmed)) {
                    $conn->executeStatement($trimmed);
                }
            }
            $conn->commit();

            $this->addFlash('success', 'Database restored successfully.');
        } catch (\Throwable $e) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->getConnection()->rollBack();
            }
            $this->addFlash('error', 'Restore failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/maintenance/clear-cache', name: 'app_settings_clear_cache', methods: ['POST'])]
    public function clearCache(KernelInterface $kernel): Response
    {
        $this->denyAccessUnlessGranted('perm.maintenance');
        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $input = new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true]);
            $output = new BufferedOutput();
            $application->run($input, $output);

            $this->addFlash('success', 'Cache cleared successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Cache clearing failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/maintenance/recalculate-reports', name: 'app_settings_recalculate', methods: ['POST'])]
    public function recalculateReports(
        SaleRepository $saleRepository,
        PurchaseRepository $purchaseRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('perm.maintenance');
        try {
            $sales = $saleRepository->findAll();
            $purchases = $purchaseRepository->findAll();

            $count = 0;
            foreach ($sales as $sale) {
                $sale->updatePaymentStatus();
                $count++;
            }
            foreach ($purchases as $purchase) {
                $purchase->updatePaymentStatus();
                $count++;
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf('Recalculated payment statuses for %d records.', $count));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Recalculating failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/maintenance/export-data', name: 'app_settings_export_data', methods: ['POST'])]
    public function exportData(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('perm.maintenance');
        try {
            $conn = $entityManager->getConnection();
            $schemaManager = $conn->createSchemaManager();
            $exportData = [];

            foreach ($schemaManager->listTables() as $table) {
                $tableName = $table->getName();
                $rows = $conn->fetchAllAssociative("SELECT * FROM `$tableName`");
                $exportData[$tableName] = $rows;
            }

            $jsonData = json_encode($exportData, JSON_PRETTY_PRINT);
            $filename = 'data-export-' . date('Y-m-d-H-i-s') . '.json';

            $response = new Response($jsonData);
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            );
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', $disposition);
            return $response;

        } catch (\Throwable $e) {
            $this->addFlash('error', 'Data export failed: ' . $e->getMessage());
            return $this->redirectToRoute('app_settings');
        }
    }
}
