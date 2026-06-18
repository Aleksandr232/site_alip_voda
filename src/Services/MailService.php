<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Models\ServiceRequest;

final class MailService
{
    public function sendNewRequestNotification(ServiceRequest $request): void
    {
        if (Config::get('MAIL_ENABLED', 'true') !== 'true') {
            return;
        }

        $recipients = $this->adminEmails();
        if ($recipients === []) {
            return;
        }

        $from = Config::get('MAIL_FROM') ?? Config::get('MAIL_USERNAME');
        if (!$from) {
            throw new \RuntimeException('Не настроен MAIL_FROM');
        }

        $fromName = Config::get('MAIL_FROM_NAME', 'СкайКлин');
        $subject = 'Новая заявка с сайта — ' . $request->serviceLabel;

        $body = $this->buildBody($request);
        $this->send($from, $fromName, $recipients, $subject, $body);
    }

    /** @return string[] */
    private function adminEmails(): array
    {
        $raw = Config::get('ADMIN_EMAILS', '');
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $email) => trim($email),
            explode(',', $raw)
        ), static fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL)));
    }

    private function buildBody(ServiceRequest $request): string
    {
        $lines = [
            'Поступила новая заявка с сайта СкайКлин.',
            '',
            'Клиент: ' . $request->clientName,
            'Телефон: ' . $request->clientPhone,
            'Услуга: ' . $request->serviceLabel,
            'Комментарий: ' . ($request->message ?: '—'),
            '',
            'Дата: ' . $request->createdAt,
            'ID заявки: ' . $request->id,
        ];

        return implode("\n", $lines);
    }

    /** @param string[] $to */
    private function send(string $from, string $fromName, array $to, string $subject, string $body): void
    {
        $host = Config::require('MAIL_HOST');
        $port = (int) Config::get('MAIL_PORT', '465');
        $username = Config::require('MAIL_USERNAME');
        $password = Config::require('MAIL_PASSWORD');
        $encryption = Config::get('MAIL_ENCRYPTION', 'ssl');

        $remote = $encryption === 'ssl'
            ? "ssl://{$host}:{$port}"
            : "{$host}:{$port}";

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );

        if (!$socket) {
            throw new \RuntimeException("SMTP: не удалось подключиться ({$errno}) {$errstr}");
        }

        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO ' . $this->hostname());
            $this->expect($socket, 250);

            $this->command($socket, 'AUTH LOGIN');
            $this->expect($socket, 334);
            $this->command($socket, base64_encode($username));
            $this->expect($socket, 334);
            $this->command($socket, base64_encode($password));
            $this->expect($socket, 235);

            $this->command($socket, 'MAIL FROM:<' . $from . '>');
            $this->expect($socket, 250);

            foreach ($to as $recipient) {
                $this->command($socket, 'RCPT TO:<' . $recipient . '>');
                $this->expect($socket, 250);
            }

            $this->command($socket, 'DATA');
            $this->expect($socket, 354);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $message = implode("\r\n", [
                'From: ' . $encodedFromName . ' <' . $from . '>',
                'To: ' . implode(', ', $to),
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $this->dotStuff($body),
            ]) . "\r\n.";

            fwrite($socket, $message . "\r\n");
            $this->expect($socket, 250);

            $this->command($socket, 'QUIT');
            $this->expect($socket, 221);
        } finally {
            fclose($socket);
        }
    }

    private function hostname(): string
    {
        return $_SERVER['SERVER_NAME'] ?? 'localhost';
    }

    private function command($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    private function expect($socket, int $code): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if (!str_starts_with($response, (string) $code)) {
            throw new \RuntimeException('SMTP error: ' . trim($response));
        }
    }

    private function dotStuff(string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $result = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            $result[] = $line;
        }

        return implode("\r\n", $result);
    }
}
