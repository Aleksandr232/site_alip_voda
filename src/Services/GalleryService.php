<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GalleryItem;
use App\Repositories\GalleryRepository;
use InvalidArgumentException;

final class GalleryService
{
    private const STATUSES = ['published', 'hidden'];

    public function __construct(
        private readonly ImageUploadService $uploader,
        private readonly GalleryRepository $gallery = new GalleryRepository(),
    ) {
    }

    public static function createDefault(string $projectRoot): self
    {
        return new self(ImageUploadService::galleryDir($projectRoot));
    }

    /** @return GalleryItem[] */
    public function listPublic(): array
    {
        return $this->gallery->all(true);
    }

    /** @return GalleryItem[] */
    public function listAdmin(): array
    {
        return $this->gallery->all(false);
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $files */
    public function create(array $input, array $files): GalleryItem
    {
        $title = trim((string) ($input['title'] ?? ''));
        $description = $this->normalizeDescription($input['description'] ?? null);
        $sortOrder = max(0, (int) ($input['sort_order'] ?? $input['sort'] ?? 0));
        $status = $this->normalizeStatus((string) ($input['status'] ?? 'published'));

        if ($title === '') {
            throw new InvalidArgumentException('Укажите название');
        }

        $beforeFile = $files['before'] ?? $files['before_image'] ?? null;
        $afterFile = $files['after'] ?? $files['after_image'] ?? null;

        if (!is_array($beforeFile) || !is_array($afterFile)) {
            throw new InvalidArgumentException('Загрузите оба фото: до и после');
        }

        $beforePath = $this->uploader->store($beforeFile, 'before_');
        $afterPath = $this->uploader->store($afterFile, 'after_');

        try {
            return $this->gallery->create($title, $description, $beforePath, $afterPath, $sortOrder, $status);
        } catch (\Throwable $e) {
            $this->uploader->deleteByPublicPath($beforePath);
            $this->uploader->deleteByPublicPath($afterPath);
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $files */
    public function update(int $id, array $input, array $files): GalleryItem
    {
        $existing = $this->gallery->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Запись не найдена');
        }

        $title = trim((string) ($input['title'] ?? $existing->title));
        $description = array_key_exists('description', $input)
            ? $this->normalizeDescription($input['description'])
            : $existing->description;
        $sortOrder = isset($input['sort_order']) || isset($input['sort'])
            ? max(0, (int) ($input['sort_order'] ?? $input['sort']))
            : $existing->sortOrder;
        $status = isset($input['status'])
            ? $this->normalizeStatus((string) $input['status'])
            : $existing->status;

        if ($title === '') {
            throw new InvalidArgumentException('Укажите название');
        }

        $beforePath = $existing->beforeImage;
        $afterPath = $existing->afterImage;
        $oldBefore = null;
        $oldAfter = null;

        $beforeFile = $files['before'] ?? $files['before_image'] ?? null;
        if (is_array($beforeFile) && (int) ($beforeFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $beforePath = $this->uploader->store($beforeFile, 'before_');
            $oldBefore = $existing->beforeImage;
        }

        $afterFile = $files['after'] ?? $files['after_image'] ?? null;
        if (is_array($afterFile) && (int) ($afterFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $afterPath = $this->uploader->store($afterFile, 'after_');
            $oldAfter = $existing->afterImage;
        }

        $item = $this->gallery->update($id, $title, $description, $beforePath, $afterPath, $sortOrder, $status);
        if (!$item) {
            throw new \RuntimeException('Не удалось обновить запись');
        }

        $this->uploader->deleteByPublicPath($oldBefore);
        $this->uploader->deleteByPublicPath($oldAfter);

        return $item;
    }

    public function delete(int $id): void
    {
        $existing = $this->gallery->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Запись не найдена');
        }

        $this->gallery->delete($id);
        $this->uploader->deleteByPublicPath($existing->beforeImage);
        $this->uploader->deleteByPublicPath($existing->afterImage);
    }

    private function normalizeDescription(mixed $value): ?string
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
}
