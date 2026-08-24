<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use InvalidArgumentException;

final class SettingsService
{
    /** @var array<string, string> */
    public const DEFAULTS = [
        'phone' => '+7 (900) 123-45-67',
        'phone_visible' => '1',
        'email' => 'info@skyclin.ru',
        'hours' => 'Пн–Сб, 8:00–20:00',
        'hero_title' => 'Моем любые объекты на высоте — осмос и WFP',
        'hero_lead' => 'Мойка фасадов и окон, монтажные работы и зимняя уборка снега с кровли — промышленными альпинистами. Высокое давление, обратный осмос, допуски СРО.',
        'stat_years' => '12+',
        'stat_objects' => '500+',
        'hero_image_main' => '',
        'hero_image_float' => '',
        'calc_price_facade' => '150',
        'calc_price_windows' => '120',
        'calc_price_snow' => '80',
        'calc_price_scaffolding' => '200',
        'calc_price_montage' => '3500',
    ];

    public function __construct(
        private readonly MediaUploadService $uploader,
        private readonly SettingsRepository $settings = new SettingsRepository(),
    ) {
    }

    public static function createDefault(string $projectRoot): self
    {
        return new self(MediaUploadService::heroMedia($projectRoot));
    }

    /** @return array<string, string> */
    public function getPublic(): array
    {
        return $this->mergeDefaults($this->settings->all());
    }

    /** @param array<string, mixed> $input */
    public function updateContacts(array $input): array
    {
        $phone = trim((string) ($input['phone'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $hours = trim((string) ($input['hours'] ?? ''));

        if ($phone === '') {
            throw new InvalidArgumentException('Укажите телефон');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Некорректный email');
        }

        if ($hours === '') {
            throw new InvalidArgumentException('Укажите режим работы');
        }

        $values = [
            'phone' => $phone,
            'phone_visible' => $this->normalizeFlag($input['phone_visible'] ?? '1'),
            'email' => $email,
            'hours' => $hours,
        ];

        $this->settings->setMany($values);

        return $this->mergeDefaults(array_merge($this->settings->all(), $values));
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $files */
    public function updateHomepage(array $input, array $files = []): array
    {
        $heroTitle = trim((string) ($input['hero_title'] ?? ''));
        $heroLead = trim((string) ($input['hero_lead'] ?? ''));
        $statYears = trim((string) ($input['stat_years'] ?? ''));
        $statObjects = trim((string) ($input['stat_objects'] ?? ''));

        if ($heroTitle === '') {
            throw new InvalidArgumentException('Укажите заголовок');
        }

        if ($heroLead === '') {
            throw new InvalidArgumentException('Укажите описание');
        }

        if ($statYears === '' || $statObjects === '') {
            throw new InvalidArgumentException('Заполните статистику');
        }

        $stored = $this->settings->all();
        $heroMain = (string) ($stored['hero_image_main'] ?? '');
        $heroFloat = (string) ($stored['hero_image_float'] ?? '');
        $oldMain = null;
        $oldFloat = null;

        if ($this->isRemoveFlag($input['remove_hero_image_main'] ?? null)) {
            if ($heroMain !== '') {
                $oldMain = $heroMain;
            }
            $heroMain = '';
        }

        if ($this->isRemoveFlag($input['remove_hero_image_float'] ?? null)) {
            if ($heroFloat !== '') {
                $oldFloat = $heroFloat;
            }
            $heroFloat = '';
        }

        $mainFile = $files['hero_image_main'] ?? null;
        if (is_array($mainFile) && (int) ($mainFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($heroMain !== '') {
                $oldMain = $heroMain;
            } elseif (($stored['hero_image_main'] ?? '') !== '') {
                $oldMain = $stored['hero_image_main'];
            }
            $heroMain = $this->uploader->store($mainFile, 'main_');
        }

        $floatFile = $files['hero_image_float'] ?? null;
        if (is_array($floatFile) && (int) ($floatFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($heroFloat !== '') {
                $oldFloat = $heroFloat;
            } elseif (($stored['hero_image_float'] ?? '') !== '') {
                $oldFloat = $stored['hero_image_float'];
            }
            $heroFloat = $this->uploader->store($floatFile, 'float_');
        }

        $values = [
            'hero_title' => $heroTitle,
            'hero_lead' => $heroLead,
            'stat_years' => $statYears,
            'stat_objects' => $statObjects,
            'hero_image_main' => $heroMain,
            'hero_image_float' => $heroFloat,
        ];

        $this->settings->setMany($values);
        $this->uploader->deleteByPublicPath($oldMain);
        $this->uploader->deleteByPublicPath($oldFloat);

        return $this->mergeDefaults(array_merge($this->settings->all(), $values));
    }

    /** @param array<string, mixed> $input */
    public function updateCalculator(array $input): array
    {
        $values = [
            'calc_price_facade' => $this->parsePrice($input['calc_price_facade'] ?? '', 'мойка фасада'),
            'calc_price_windows' => $this->parsePrice($input['calc_price_windows'] ?? '', 'мойка окон'),
            'calc_price_snow' => $this->parsePrice($input['calc_price_snow'] ?? '', 'уборка снега'),
            'calc_price_scaffolding' => $this->parsePrice($input['calc_price_scaffolding'] ?? '', 'установка лесов'),
            'calc_price_montage' => $this->parsePrice($input['calc_price_montage'] ?? '', 'монтаж'),
        ];

        $this->settings->setMany($values);

        return $this->mergeDefaults(array_merge($this->settings->all(), $values));
    }

    private function parsePrice(mixed $value, string $label): string
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            throw new InvalidArgumentException("Укажите корректную цену: {$label}");
        }

        $price = (float) $normalized;
        if ($price < 0) {
            throw new InvalidArgumentException("Цена не может быть отрицательной: {$label}");
        }

        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    /** @param array<string, string> $stored */
    private function mergeDefaults(array $stored): array
    {
        $result = self::DEFAULTS;

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $stored) && $stored[$key] !== '') {
                $result[$key] = $stored[$key];
            }
        }

        return $result;
    }

    private function normalizeFlag(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    private function isRemoveFlag(mixed $value): bool
    {
        return $this->normalizeFlag($value) === '1';
    }
}
