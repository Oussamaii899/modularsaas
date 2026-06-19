<?php

namespace App\Twig;

use App\Repository\SettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    private ?array $settings = null;

    public function __construct(
        private SettingRepository $settingRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', [$this, 'getSetting']),
        ];
    }

    public function getSetting(string $key, ?string $default = null): ?string
    {
        if ($this->settings === null) {
            $this->settings = [];
            try {
                $all = $this->settingRepository->findAll();
                foreach ($all as $s) {
                    $this->settings[$s->getKeyName()] = $s->getValue();
                }
            } catch (\Exception $e) {
                // Graceful fallback if database/table is not initialized or migrated
            }
        }

        return $this->settings[$key] ?? $default;
    }
}
