<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Models\GalleryItem;

final class GalleryRepository
{
    public function findById(int $id): ?GalleryItem
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, description, before_image, after_image, sort_order, status, created_at
             FROM gallery_items WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    /** @return GalleryItem[] */
    public function all(bool $publishedOnly = false): array
    {
        $sql = 'SELECT id, title, description, before_image, after_image, sort_order, status, created_at
                FROM gallery_items';

        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }

        $sql .= ' ORDER BY sort_order ASC, id DESC';

        $stmt = Database::connection()->query($sql);

        return array_map(fn (array $row) => $this->map($row), $stmt->fetchAll());
    }

    public function create(
        string $title,
        ?string $description,
        string $beforeImage,
        string $afterImage,
        int $sortOrder,
        string $status,
    ): GalleryItem {
        $stmt = Database::connection()->prepare(
            'INSERT INTO gallery_items (title, description, before_image, after_image, sort_order, status)
             VALUES (:title, :description, :before_image, :after_image, :sort_order, :status)'
        );
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'before_image' => $beforeImage,
            'after_image' => $afterImage,
            'sort_order' => $sortOrder,
            'status' => $status,
        ]);

        $item = $this->findById((int) Database::connection()->lastInsertId());
        if (!$item) {
            throw new \RuntimeException('Не удалось создать запись');
        }

        return $item;
    }

    public function update(
        int $id,
        string $title,
        ?string $description,
        string $beforeImage,
        string $afterImage,
        int $sortOrder,
        string $status,
    ): ?GalleryItem {
        $stmt = Database::connection()->prepare(
            'UPDATE gallery_items
             SET title = :title,
                 description = :description,
                 before_image = :before_image,
                 after_image = :after_image,
                 sort_order = :sort_order,
                 status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'before_image' => $beforeImage,
            'after_image' => $afterImage,
            'sort_order' => $sortOrder,
            'status' => $status,
        ]);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM gallery_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function countPublished(): int
    {
        $stmt = Database::connection()->query(
            "SELECT COUNT(*) FROM gallery_items WHERE status = 'published'"
        );

        return (int) $stmt->fetchColumn();
    }

    private function map(array $row): GalleryItem
    {
        return new GalleryItem(
            (int) $row['id'],
            $row['title'],
            $row['description'],
            $row['before_image'],
            $row['after_image'],
            (int) $row['sort_order'],
            $row['status'],
            $row['created_at'],
        );
    }
}
