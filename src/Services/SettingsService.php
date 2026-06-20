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
        'hero_title' => 'Чистота и безопасность на высоте без лесов и подъёмников',
        'hero_lead' => 'Мойка фасадов и окон, монтажные работы и зимняя уборка снега с кровли — промышленными альпинистами. Высокое давление, обратный осмос, допуски СРО.',
        'stat_years' => '12+',
        'stat_objects' => '500+',
    ];

    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository(),
    ) {
    }

    /** @return array<string, string> */
    public function getPublic(): array
    {
        return $this->mergeDefaults($this->settings->all());
    }

    /** @return array<string, string> */
    public function getAdmin(): array
    {
        return $this->getPublic();
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

    /** @param array<string, mixed> $input */
    public function updateHomepage(array $input): array
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

        $values = [
            'hero_title' => $heroTitle,
            'hero_lead' => $heroLead,
            'stat_years' => $statYears,
            'stat_objects' => $statObjects,
        ];

        $this->settings->setMany($values);

        return $this->mergeDefaults(array_merge($this->settings->all(), $values));
    }

    /** @param array<string, string> $stored */
    private function mergeDefaults(array $stored): array
    {
        $result = self::DEFAULTS;

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (isset($stored[$key]) && $stored[$key] !== '') {
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
}
