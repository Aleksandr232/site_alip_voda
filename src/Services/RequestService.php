<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceRequest;
use App\Repositories\ClientRepository;
use App\Repositories\RequestRepository;
use InvalidArgumentException;

final class RequestService
{
    private const SERVICE_LABELS = [
        'facade' => 'Мойка фасада',
        'windows' => 'Мойка окон',
        'montage' => 'Монтажные работы',
        'snow' => 'Уборка снега с кровли',
        'complex' => 'Комплекс работ',
        'alpinism' => 'Альпинистские работы',
    ];

    private const STATUSES = ['new', 'in_progress', 'done'];

    public function __construct(
        private readonly ClientRepository $clients = new ClientRepository(),
        private readonly RequestRepository $requests = new RequestRepository(),
        private readonly MailService $mail = new MailService(),
    ) {
    }

    public static function serviceLabel(string $type): string
    {
        return self::SERVICE_LABELS[$type] ?? $type;
    }

    public function create(string $name, string $phone, string $serviceType, ?string $message): ServiceRequest
    {
        $name = trim($name);
        $phone = $this->normalizePhone($phone);
        $message = $message !== null ? trim($message) : null;

        if ($name === '' || mb_strlen($name) < 2) {
            throw new InvalidArgumentException('Укажите имя');
        }

        if ($phone === '') {
            throw new InvalidArgumentException('Укажите телефон');
        }

        if (!isset(self::SERVICE_LABELS[$serviceType])) {
            throw new InvalidArgumentException('Выберите тип услуги');
        }

        $client = $this->clients->findByPhone($phone);
        if ($client) {
            if ($client->name !== $name) {
                $this->clients->updateName($client->id, $name);
            }
            $clientId = $client->id;
        } else {
            $clientId = $this->clients->create($name, $phone)->id;
        }

        $request = $this->requests->create($clientId, $serviceType, $message ?: null);

        try {
            $this->mail->sendNewRequestNotification($request);
        } catch (\Throwable $e) {
            error_log('Mail error: ' . $e->getMessage());
        }

        return $request;
    }

    /** @return ServiceRequest[] */
    public function list(?string $status = null): array
    {
        if ($status !== null && $status !== '' && !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Некорректный статус');
        }

        return $this->requests->all($status);
    }

    public function updateStatus(int $id, string $status): ServiceRequest
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Некорректный статус');
        }

        $request = $this->requests->updateStatus($id, $status);
        if (!$request) {
            throw new InvalidArgumentException('Заявка не найдена');
        }

        return $request;
    }

    public function delete(int $id): void
    {
        $request = $this->requests->findById($id);
        if (!$request) {
            throw new InvalidArgumentException('Заявка не найдена');
        }

        $this->requests->delete($id);
    }

    public function stats(): array
    {
        return [
            'new' => $this->requests->countByStatus('new'),
            'in_progress' => $this->requests->countByStatus('in_progress'),
            'done' => $this->requests->countByStatus('done'),
            'clients' => $this->clients->countAll(),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7' . substr($digits, 1);
        }

        if (str_starts_with($digits, '7') && strlen($digits) === 11) {
            return '+' . $digits;
        }

        return '+' . $digits;
    }
}
