<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Models\BlogPost;

final class BlogPostRepository
{
    public function findById(int $id): ?BlogPost
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, slug, description, keywords, content, cover_image, video_path, status, created_at, updated_at
             FROM blog_posts WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, slug, description, keywords, content, cover_image, video_path, status, created_at, updated_at
             FROM blog_posts WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM blog_posts WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return BlogPost[] */
    public function all(bool $publishedOnly = false): array
    {
        $sql = 'SELECT id, title, slug, description, keywords, content, cover_image, video_path, status, created_at, updated_at
                FROM blog_posts';

        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = Database::connection()->query($sql);

        return array_map(fn (array $row) => $this->map($row), $stmt->fetchAll());
    }

    public function create(
        string $title,
        string $slug,
        ?string $description,
        ?string $keywords,
        ?string $content,
        ?string $coverImage,
        ?string $videoPath,
        string $status,
    ): BlogPost {
        $stmt = Database::connection()->prepare(
            'INSERT INTO blog_posts (title, slug, description, keywords, content, cover_image, video_path, status)
             VALUES (:title, :slug, :description, :keywords, :content, :cover_image, :video_path, :status)'
        );
        $stmt->execute([
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'keywords' => $keywords,
            'content' => $content,
            'cover_image' => $coverImage,
            'video_path' => $videoPath,
            'status' => $status,
        ]);

        $post = $this->findById((int) Database::connection()->lastInsertId());
        if (!$post) {
            throw new \RuntimeException('Не удалось создать статью');
        }

        return $post;
    }

    public function update(
        int $id,
        string $title,
        string $slug,
        ?string $description,
        ?string $keywords,
        ?string $content,
        ?string $coverImage,
        ?string $videoPath,
        string $status,
    ): ?BlogPost {
        $stmt = Database::connection()->prepare(
            'UPDATE blog_posts
             SET title = :title, slug = :slug, description = :description, keywords = :keywords,
                 content = :content, cover_image = :cover_image, video_path = :video_path, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'keywords' => $keywords,
            'content' => $content,
            'cover_image' => $coverImage,
            'video_path' => $videoPath,
            'status' => $status,
        ]);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM blog_posts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function countPublished(): int
    {
        return (int) Database::connection()->query(
            "SELECT COUNT(*) FROM blog_posts WHERE status = 'published'"
        )->fetchColumn();
    }

    private function map(array $row): BlogPost
    {
        return new BlogPost(
            (int) $row['id'],
            $row['title'],
            $row['slug'],
            $row['description'],
            $row['keywords'],
            $row['content'],
            $row['cover_image'],
            $row['video_path'],
            $row['status'],
            $row['created_at'],
            $row['updated_at'],
        );
    }
}
