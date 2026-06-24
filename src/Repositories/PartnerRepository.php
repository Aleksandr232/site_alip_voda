<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Database\Installer;
use App\Models\Partner;
use PDO;

final class PartnerRepository
{
    private static ?bool $hasLogoBackground = null;

    public function findById(int $id): ?Partner
    {
        $stmt = Database::connection()->prepare(
            'SELECT ' . $this->selectColumns() . ' FROM partners WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    /** @return Partner[] */
    public function all(bool $publishedOnly = false): array
    {
        $sql = 'SELECT ' . $this->selectColumns() . ' FROM partners';

        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = Database::connection()->query($sql);

        return array_map(fn (array $row) => $this->map($row), $stmt->fetchAll());
    }

    public function create(
        string $name,
        ?string $website,
        string $logoImage,
        ?string $logoBackground,
        int $sortOrder,
        string $status,
    ): Partner {
        $pdo = Database::connection();

        if ($this->supportsLogoBackground()) {
            $stmt = $pdo->prepare(
                'INSERT INTO partners (name, website, logo_image, logo_background, sort_order, status)
                 VALUES (:name, :website, :logo_image, :logo_background, :sort_order, :status)'
            );
            $stmt->execute([
                'name' => $name,
                'website' => $website,
                'logo_image' => $logoImage,
                'logo_background' => $logoBackground,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO partners (name, website, logo_image, sort_order, status)
                 VALUES (:name, :website, :logo_image, :sort_order, :status)'
            );
            $stmt->execute([
                'name' => $name,
                'website' => $website,
                'logo_image' => $logoImage,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        }

        $id = (int) $pdo->lastInsertId();
        if ($id <= 0) {
            throw new \RuntimeException('Не удалось создать партнёра: не получен ID записи');
        }

        $partner = $this->findById($id);
        if (!$partner) {
            throw new \RuntimeException('Не удалось создать партнёра: запись не найдена после сохранения');
        }

        return $partner;
    }

    public function update(
        int $id,
        string $name,
        ?string $website,
        string $logoImage,
        ?string $logoBackground,
        int $sortOrder,
        string $status,
    ): ?Partner {
        if ($this->supportsLogoBackground()) {
            $stmt = Database::connection()->prepare(
                'UPDATE partners
                 SET name = :name, website = :website, logo_image = :logo_image,
                     logo_background = :logo_background, sort_order = :sort_order, status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'website' => $website,
                'logo_image' => $logoImage,
                'logo_background' => $logoBackground,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        } else {
            $stmt = Database::connection()->prepare(
                'UPDATE partners
                 SET name = :name, website = :website, logo_image = :logo_image,
                     sort_order = :sort_order, status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'website' => $website,
                'logo_image' => $logoImage,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        }

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM partners WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function selectColumns(): string
    {
        $columns = 'id, name, website, logo_image, sort_order, status, created_at';

        if ($this->supportsLogoBackground()) {
            $columns = 'id, name, website, logo_image, logo_background, sort_order, status, created_at';
        }

        return $columns;
    }

    private function supportsLogoBackground(): bool
    {
        if (self::$hasLogoBackground !== null) {
            return self::$hasLogoBackground;
        }

        try {
            Installer::ensurePartnerColumns();
            self::$hasLogoBackground = $this->columnExists('logo_background');
        } catch (\Throwable) {
            self::$hasLogoBackground = false;
        }

        return self::$hasLogoBackground;
    }

    private function columnExists(string $column): bool
    {
        $pdo = Database::connection();
        $safeColumn = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $column);
        $stmt = $pdo->query("SHOW COLUMNS FROM partners LIKE '{$safeColumn}'");

        return (bool) $stmt?->fetch(PDO::FETCH_ASSOC);
    }

    private function map(array $row): Partner
    {
        return new Partner(
            (int) $row['id'],
            $row['name'],
            $row['website'] ?? null,
            $row['logo_image'],
            isset($row['logo_background']) && $row['logo_background'] !== ''
                ? (string) $row['logo_background']
                : null,
            (int) $row['sort_order'],
            $row['status'],
            $row['created_at'],
        );
    }
}
