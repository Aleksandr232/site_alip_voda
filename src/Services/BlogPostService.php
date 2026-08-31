<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Repositories\BlogPostRepository;
use InvalidArgumentException;

final class BlogPostService
{
    private const STATUSES = ['published', 'draft'];

    public function __construct(
        private readonly MediaUploadService $coverUploader,
        private readonly MediaUploadService $videoUploader,
        private readonly BlogPostRepository $posts = new BlogPostRepository(),
        private readonly SlugService $slugs = new SlugService(),
    ) {
    }

    public static function createDefault(string $projectRoot): self
    {
        return new self(
            MediaUploadService::blogCover($projectRoot),
            MediaUploadService::blogVideo($projectRoot),
        );
    }

    /** @return BlogPost[] */
    public function listPublic(): array
    {
        return $this->posts->all(true);
    }

    /** @return BlogPost[] */
    public function listAdmin(): array
    {
        return $this->posts->all(false);
    }

    public function getBySlug(string $slug, bool $admin = false): BlogPost
    {
        $post = $this->posts->findBySlug($slug);
        if (!$post) {
            throw new InvalidArgumentException('Статья не найдена');
        }

        if (!$admin && $post->status !== 'published') {
            throw new InvalidArgumentException('Статья не найдена');
        }

        return $post;
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $files */
    public function create(array $input, array $files): BlogPost
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Укажите заголовок');
        }

        $slug = $this->resolveSlug($title, $input['slug'] ?? null, null);
        $description = $this->nullableString($input['description'] ?? null);
        $keywords = $this->nullableString($input['keywords'] ?? null);
        $content = $this->nullableString($input['content'] ?? null);
        $status = $this->normalizeStatus((string) ($input['status'] ?? 'draft'));

        $coverImage = $this->uploadIfPresent($files, 'cover', $this->coverUploader, 'cover_');
        $videoPath = $this->uploadIfPresent($files, 'video', $this->videoUploader, 'video_');

        try {
            $post = $this->posts->create($title, $slug, $description, $keywords, $content, $coverImage, $videoPath, $status);
            $this->notifySearchEngines($post);
            return $post;
        } catch (\Throwable $e) {
            $this->coverUploader->deleteByPublicPath($coverImage);
            $this->videoUploader->deleteByPublicPath($videoPath);
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $files */
    public function update(int $id, array $input, array $files): BlogPost
    {
        $existing = $this->posts->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Статья не найдена');
        }

        $title = trim((string) ($input['title'] ?? $existing->title));
        if ($title === '') {
            throw new InvalidArgumentException('Укажите заголовок');
        }

        $slug = $this->resolveSlug($title, $input['slug'] ?? $existing->slug, $id);
        $description = array_key_exists('description', $input)
            ? $this->nullableString($input['description'])
            : $existing->description;
        $keywords = array_key_exists('keywords', $input)
            ? $this->nullableString($input['keywords'])
            : $existing->keywords;
        $content = array_key_exists('content', $input)
            ? $this->nullableString($input['content'])
            : $existing->content;
        $status = isset($input['status'])
            ? $this->normalizeStatus((string) $input['status'])
            : $existing->status;

        $coverImage = $existing->coverImage;
        $videoPath = $existing->videoPath;
        $oldCover = null;
        $oldVideo = null;

        $newCover = $this->uploadIfPresent($files, 'cover', $this->coverUploader, 'cover_');
        if ($newCover !== null) {
            $oldCover = $coverImage;
            $coverImage = $newCover;
        }

        $newVideo = $this->uploadIfPresent($files, 'video', $this->videoUploader, 'video_');
        if ($newVideo !== null) {
            $oldVideo = $videoPath;
            $videoPath = $newVideo;
        }

        if (isset($input['remove_video']) && (string) $input['remove_video'] === '1') {
            $oldVideo = $videoPath;
            $videoPath = null;
        }

        $post = $this->posts->update($id, $title, $slug, $description, $keywords, $content, $coverImage, $videoPath, $status);
        if (!$post) {
            throw new \RuntimeException('Не удалось обновить статью');
        }

        $this->coverUploader->deleteByPublicPath($oldCover);
        $this->videoUploader->deleteByPublicPath($oldVideo);
        $this->notifySearchEngines($post);

        return $post;
    }

    public function delete(int $id): void
    {
        $existing = $this->posts->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Статья не найдена');
        }

        $this->posts->delete($id);
        $this->coverUploader->deleteByPublicPath($existing->coverImage);
        $this->videoUploader->deleteByPublicPath($existing->videoPath);
        SearchPingService::pingSitemap();
    }

  /** @param array<string, mixed> $files */
    private function uploadIfPresent(array $files, string $key, MediaUploadService $uploader, string $prefix): ?string
    {
        $file = $files[$key] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $uploader->store($file, $prefix);
    }

    private function resolveSlug(string $title, mixed $slugInput, ?int $excludeId): string
    {
        $slug = trim((string) ($slugInput ?? ''));
        $slug = $slug !== '' ? $this->slugs->slugify($slug) : $this->slugs->slugify($title);

        if ($this->posts->slugExists($slug, $excludeId)) {
            return $this->slugs->unique($slug, fn (string $candidate) => $this->posts->slugExists($candidate, $excludeId));
        }

        return $slug;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizeStatus(string $status): string
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Некорректный статус');
        }

        return $status;
    }

    private function notifySearchEngines(BlogPost $post): void
    {
        if ($post->status !== 'published') {
            return;
        }

        SearchPingService::pingSitemap();
    }
}
