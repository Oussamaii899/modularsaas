<?php

namespace App\Twig;

use App\Repository\SettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ModuleExtension extends AbstractExtension
{
    private ?string $activeModule = null;

    public function __construct(
        private SettingRepository $settingRepository
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('module_term', [$this, 'getModuleTerm']),
        ];
    }

    public function getModuleTerm(string $word): string
    {
        if ($this->activeModule === null) {
            try {
                $setting = $this->settingRepository->findOneBy(['keyName' => 'active_module']);
                $this->activeModule = $setting ? $setting->getValue() : 'none';
            } catch (\Exception $e) {
                $this->activeModule = 'none';
            }
        }

        $wordLower = strtolower($word);
        
        // Define terminology maps
        $doctorMap = [
            'client' => 'patient',
            'clients' => 'patients',
            'customer' => 'patient',
            'customers' => 'patients',
            'supplier' => 'pharmacy',
            'suppliers' => 'pharmacies',
            'sale' => 'consultation',
            'sales' => 'consultations',
            'purchase' => 'pharmacy order',
            'purchases' => 'pharmacy orders',
            'product' => 'medication',
            'products' => 'medications',
            'invoice' => 'medical receipt',
            'invoices' => 'medical receipts',
            'revenue' => 'income',
            'revenues' => 'income',
            
            // Phrases
            'sales list' => 'consultations',
            'purchase list' => 'pharmacy orders',
            'new sale' => 'new consultation',
            'new purchase' => 'new order',
            'new client' => 'register patient',
            'new customer' => 'register patient',
            'new supplier' => 'new pharmacy',
            'sales records' => 'consultations',
            'purchase records' => 'pharmacy orders',
            'customer transactions' => 'patient transactions',
            'supplier records' => 'pharmacy records',
            'product records' => 'medication records',
            'client details' => 'patient details',
            'customer details' => 'patient details',
            'customer information' => 'patient information',
            'customer activity' => 'patient activity',
            'recent customer activity' => 'recent patient activity',
            'view all customers' => 'view all patients',
            'client info' => 'patient info',
            'client information' => 'patient information',
            'supplier info' => 'pharmacy info',
            'supplier details' => 'pharmacy details',
            'supplier information' => 'pharmacy information',
            'product details' => 'medication details',
            'product info' => 'medication info',
            'product information' => 'medication information',
            'sale details' => 'consultation details',
            'purchase details' => 'order details',
        ];

        $teacherMap = [
            'client' => 'student',
            'clients' => 'students',
            'customer' => 'student',
            'customers' => 'students',
            'supplier' => 'supplier',
            'suppliers' => 'suppliers',
            'sale' => 'class',
            'sales' => 'classes',
            'purchase' => 'expense',
            'purchases' => 'expenses',
            'product' => 'course',
            'products' => 'courses',
            'invoice' => 'receipt',
            'invoices' => 'receipts',
            'revenue' => 'income',
            'revenues' => 'income',
            
            // Phrases
            'sales list' => 'classes',
            'purchase list' => 'expenses',
            'new sale' => 'new class',
            'new purchase' => 'new expense',
            'new client' => 'register student',
            'new customer' => 'register student',
            'new supplier' => 'new supplier',
            'sales records' => 'classes',
            'purchase records' => 'expenses',
            'customer transactions' => 'student transactions',
            'supplier records' => 'supplier records',
            'product records' => 'course records',
            'client details' => 'student details',
            'customer details' => 'student details',
            'customer information' => 'student information',
            'customer activity' => 'student activity',
            'recent customer activity' => 'recent student activity',
            'view all customers' => 'view all students',
            'client info' => 'student info',
            'client information' => 'student information',
            'supplier info' => 'supplier info',
            'supplier details' => 'supplier details',
            'supplier information' => 'supplier information',
            'product details' => 'course details',
            'product info' => 'course info',
            'product information' => 'course information',
            'sale details' => 'class details',
            'purchase details' => 'expense details',
        ];

        $currentMap = [];
        if ($this->activeModule === 'doctor') {
            $currentMap = $doctorMap;
        } elseif ($this->activeModule === 'teacher') {
            $currentMap = $teacherMap;
        }

        if (isset($currentMap[$wordLower])) {
            $translated = $currentMap[$wordLower];
            
            // Match casing
            if (ctype_upper($word)) {
                return strtoupper($translated);
            }
            if (ctype_upper(substr($word, 0, 1))) {
                return ucfirst($translated);
            }
            return $translated;
        }

        return $word;
    }
}
