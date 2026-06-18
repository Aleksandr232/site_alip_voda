<?php

declare(strict_types=1);

namespace App\Models;

final class ServiceRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $clientId,
        public readonly string $clientName,
        public readonly string $clientPhone,
        public readonly string $serviceType,
        public readonly string $serviceLabel,
        public readonly ?string $message,
        public readonly string $status,
        public readonly string $createdAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
            'client_phone' => $this->clientPhone,
            'service_type' => $this->serviceType,
            'service_label' => $this->serviceLabel,
            'message' => $this->message,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
