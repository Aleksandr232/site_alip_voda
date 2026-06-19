<?php

declare(strict_types=1);

namespace App\Models;

final class BlogPost
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly ?string $keywords,
        public readonly ?string $content,
        public readonly ?string $coverImage,
        public readonly ?string $videoPath,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'content' => $this->content,
            'cover_image' => $this->coverImage,
            'video_path' => $this->videoPath,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
