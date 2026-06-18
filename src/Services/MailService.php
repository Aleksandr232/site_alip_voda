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
        $subject = 'Новая заявка: ' . $request->serviceLabel . ' — ' . $request->clientName;

        $textBody = $this->buildTextBody($request);
        $htmlBody = $this->buildHtmlBody($request);
        $this->send($from, $fromName, $recipients, $subject, $textBody, $htmlBody);
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

    private function buildTextBody(ServiceRequest $request): string
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
            'Номер заявки: #' . $request->id,
        ];

        return implode("\n", $lines);
    }

    private function buildHtmlBody(ServiceRequest $request): string
    {
        $name = htmlspecialchars($request->clientName, ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($request->clientPhone, ENT_QUOTES, 'UTF-8');
        $service = htmlspecialchars($request->serviceLabel, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($request->message ?: '—', ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($request->createdAt, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f5f7fa;color:#1a1a1a">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #e5e7eb">
    <tr>
      <td style="padding:24px 28px">
        <h2 style="margin:0 0 16px;font-size:20px">Новая заявка с сайта</h2>
        <p style="margin:0 0 20px;color:#6b7280">СкайКлин — уведомление для администратора</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6">
          <tr><td style="padding:8px 0;color:#6b7280;width:120px">Клиент</td><td style="padding:8px 0"><strong>{$name}</strong></td></tr>
          <tr><td style="padding:8px 0;color:#6b7280">Телефон</td><td style="padding:8px 0"><a href="tel:{$phone}" style="color:#2563eb">{$phone}</a></td></tr>
          <tr><td style="padding:8px 0;color:#6b7280">Услуга</td><td style="padding:8px 0">{$service}</td></tr>
          <tr><td style="padding:8px 0;color:#6b7280;vertical-align:top">Комментарий</td><td style="padding:8px 0">{$message}</td></tr>
          <tr><td style="padding:8px 0;color:#6b7280">Дата</td><td style="padding:8px 0">{$date}</td></tr>
          <tr><td style="padding:8px 0;color:#6b7280">Заявка</td><td style="padding:8px 0">#{$request->id}</td></tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /** @param string[] $to */
    private function send(
        string $from,
        string $fromName,
        array $to,
        string $subject,
        string $textBody,
        string $htmlBody
    ): void {
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
            $this->command($socket, 'EHLO ' . $this->ehloDomain($from));
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

            $boundary = 'skyclin_' . bin2hex(random_bytes(8));
            $domain = $this->ehloDomain($from);
            $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(12)), $domain);
            $date = gmdate('D, d M Y H:i:s') . ' +0000';

            $encodedSubject = $this->encodeHeader($subject);
            $encodedFromName = $this->encodeHeader($fromName);

            $headers = [
                'Date: ' . $date,
                'Message-ID: ' . $messageId,
                'From: ' . $encodedFromName . ' <' . $from . '>',
                'To: ' . implode(', ', $to),
                'Reply-To: ' . $from,
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                'Auto-Submitted: auto-generated',
                'X-Auto-Response-Suppress: All',
            ];

            $body = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                . $this->quotedPrintable($textBody) . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                . $this->quotedPrintable($htmlBody) . "\r\n"
                . "--{$boundary}--";

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

            fwrite($socket, $message . "\r\n");
            $this->expect($socket, 250);

            $this->command($socket, 'QUIT');
            $this->expect($socket, 221);
        } finally {
            fclose($socket);
        }
    }

    private function ehloDomain(string $from): string
    {
        $configured = Config::get('MAIL_EHLO_DOMAIN');
        if ($configured) {
            return $configured;
        }

        if (str_contains($from, '@')) {
            return substr($from, strpos($from, '@') + 1);
        }

        return $_SERVER['SERVER_NAME'] ?? 'localhost';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function quotedPrintable(string $text): string
    {
        if (function_exists('quoted_printable_encode')) {
            return quoted_printable_encode($text);
        }

        return $text;
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
}
