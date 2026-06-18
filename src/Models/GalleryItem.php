<?php

declare(strict_types=1);

namespace App\Models;

final class GalleryItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $beforeImage,
        public readonly string $afterImage,
        public readonly int $sortOrder,
        public readonly string $status,
        public readonly string $createdAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'before_image' => $this->beforeImage,
            'after_image' => $this->afterImage,
            'sort_order' => $this->sortOrder,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
