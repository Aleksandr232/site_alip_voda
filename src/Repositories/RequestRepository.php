<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Models\ServiceRequest;
use App\Services\RequestService;

final class RequestRepository
{
    public function create(int $clientId, string $serviceType, ?string $message): ServiceRequest
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO requests (client_id, service_type, message, status)
             VALUES (:client_id, :service_type, :message, :status)'
        );
        $stmt->execute([
            'client_id' => $clientId,
            'service_type' => $serviceType,
            'message' => $message,
            'status' => 'new',
        ]);

        $request = $this->findById((int) Database::connection()->lastInsertId());
        if (!$request) {
            throw new \RuntimeException('Не удалось создать заявку');
        }

        return $request;
    }

    public function findById(int $id): ?ServiceRequest
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.client_id, r.service_type, r.message, r.status, r.created_at,
                    c.name AS client_name, c.phone AS client_phone
             FROM requests r
             INNER JOIN clients c ON c.id = r.client_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    /** @return ServiceRequest[] */
    public function all(?string $status = null): array
    {
        $sql = 'SELECT r.id, r.client_id, r.service_type, r.message, r.status, r.created_at,
                       c.name AS client_name, c.phone AS client_phone
                FROM requests r
                INNER JOIN clients c ON c.id = r.client_id';

        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE r.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->map($row), $stmt->fetchAll());
    }

    public function countByStatus(string $status): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM requests WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    public function updateStatus(int $id, string $status): ?ServiceRequest
    {
        $stmt = Database::connection()->prepare('UPDATE requests SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function map(array $row): ServiceRequest
    {
        return new ServiceRequest(
            (int) $row['id'],
            (int) $row['client_id'],
            $row['client_name'],
            $row['client_phone'],
            $row['service_type'],
            RequestService::serviceLabel($row['service_type']),
            $row['message'],
            $row['status'],
            $row['created_at'],
        );
    }
}
