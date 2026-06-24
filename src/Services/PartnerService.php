<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Partner;
use App\Repositories\PartnerRepository;
use InvalidArgumentException;

final class PartnerService
{
    private const STATUSES = ['published', 'hidden'];

    public function __construct(
        private readonly MediaUploadService $uploader,
        private readonly PartnerRepository $partners = new PartnerRepository(),
    ) {
    }

    public static function createDefault(string $projectRoot): self
    {
        return new self(MediaUploadService::partnerLogo($projectRoot));
    }

    /** @return Partner[] */
    public function listPublic(): array
    {
        return $this->partners->all(true);
    }

    /** @return Partner[] */
    public function listAdmin(): array
    {
        return $this->partners->all(false);
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $files */
    public function create(array $input, array $files): Partner
    {
        $name = trim((string) ($input['name'] ?? ''));
        $website = $this->normalizeWebsite($input['website'] ?? null);
        $sortOrder = max(0, (int) ($input['sort_order'] ?? $input['sort'] ?? 0));
        $status = $this->normalizeStatus((string) ($input['status'] ?? 'published'));
        $logoBackground = $this->normalizeLogoBackground($input['logo_background'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('Укажите название организации');
        }

        $logoFile = $files['logo'] ?? $files['image'] ?? null;
        if (!is_array($logoFile)) {
            throw new InvalidArgumentException('Загрузите логотип');
        }

        $logoPath = $this->uploader->store($logoFile, 'logo_');

        try {
            return $this->partners->create($name, $website, $logoPath, $logoBackground, $sortOrder, $status);
        } catch (\Throwable $e) {
            $this->uploader->deleteByPublicPath($logoPath);
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $files */
    public function update(int $id, array $input, array $files): Partner
    {
        $existing = $this->partners->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Партнёр не найден');
        }

        $name = trim((string) ($input['name'] ?? $existing->name));
        $website = array_key_exists('website', $input)
            ? $this->normalizeWebsite($input['website'])
            : $existing->website;
        $sortOrder = isset($input['sort_order']) || isset($input['sort'])
            ? max(0, (int) ($input['sort_order'] ?? $input['sort']))
            : $existing->sortOrder;
        $status = isset($input['status'])
            ? $this->normalizeStatus((string) $input['status'])
            : $existing->status;
        $logoBackground = array_key_exists('logo_background', $input)
            ? $this->normalizeLogoBackground($input['logo_background'])
            : $existing->logoBackground;

        if ($name === '') {
            throw new InvalidArgumentException('Укажите название организации');
        }

        $logoPath = $existing->logoImage;
        $oldLogo = null;

        $logoFile = $files['logo'] ?? $files['image'] ?? null;
        if (is_array($logoFile) && (int) ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $logoPath = $this->uploader->store($logoFile, 'logo_');
            $oldLogo = $existing->logoImage;
        }

        $partner = $this->partners->update($id, $name, $website, $logoPath, $logoBackground, $sortOrder, $status);
        if (!$partner) {
            throw new \RuntimeException('Не удалось обновить партнёра');
        }

        $this->uploader->deleteByPublicPath($oldLogo);

        return $partner;
    }

    public function delete(int $id): void
    {
        $existing = $this->partners->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Партнёр не найден');
        }

        $this->partners->delete($id);
        $this->uploader->deleteByPublicPath($existing->logoImage);
    }

    private function normalizeStatus(string $status): string
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Некорректный статус');
        }

        return $status;
    }

    private function normalizeWebsite(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Некорректный адрес сайта');
        }

        return $url;
    }

    private function normalizeLogoBackground(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $color = trim((string) $value);
        if ($color === '') {
            return null;
        }

        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            throw new InvalidArgumentException('Некорректный цвет фона');
        }

        if (strlen($color) === 4) {
            return sprintf(
                '#%s%s%s',
                str_repeat($color[1], 2),
                str_repeat($color[2], 2),
                str_repeat($color[3], 2)
            );
        }

        return strtoupper($color);
    }
}
