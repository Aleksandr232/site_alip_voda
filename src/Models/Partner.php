<?php

declare(strict_types=1);

namespace App\Models;

final class Partner
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $logoImage,
        public readonly int $sortOrder,
        public readonly string $status,
        public readonly string $createdAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_image' => $this->logoImage,
            'sort_order' => $this->sortOrder,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
