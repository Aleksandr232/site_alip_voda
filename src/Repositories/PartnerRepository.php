<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Models\Partner;

final class PartnerRepository
{
    public function findById(int $id): ?Partner
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, website, logo_image, sort_order, status, created_at
             FROM partners WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    /** @return Partner[] */
    public function all(bool $publishedOnly = false): array
    {
        $sql = 'SELECT id, name, website, logo_image, sort_order, status, created_at FROM partners';

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
        int $sortOrder,
        string $status,
    ): Partner {
        $pdo = Database::connection();
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
        int $sortOrder,
        string $status,
    ): ?Partner {
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

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM partners WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function map(array $row): Partner
    {
        return new Partner(
            (int) $row['id'],
            $row['name'],
            $row['website'] ?? null,
            $row['logo_image'],
            (int) $row['sort_order'],
            $row['status'],
            $row['created_at'],
        );
    }
}
