<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class ImageUploadService
{
    private const MAX_BYTES = 5_242_880;

    /** @var array<string, string> */
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly string $uploadDir,
    ) {
    }

    public static function galleryDir(string $projectRoot): self
    {
        return new self($projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'gallery');
    }

    /** @param array<string, mixed> $file */
    public function store(array $file, string $prefix = ''): string
    {
        if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
            throw new InvalidArgumentException('Файл не загружен');
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($this->uploadErrorMessage((int) $file['error']));
        }

        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new InvalidArgumentException('Файл слишком большой (макс. 5 МБ)');
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Некорректная загрузка файла');
        }

        $mime = $this->detectMime($tmp);
        if (!isset(self::MIME_TO_EXT[$mime])) {
            throw new InvalidArgumentException('Допустимы только JPG, PNG, WEBP и GIF');
        }

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
            throw new \RuntimeException('Не удалось создать папку для загрузок');
        }

        $filename = $prefix . bin2hex(random_bytes(8)) . '.' . self::MIME_TO_EXT[$mime];
        $target = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Не удалось сохранить файл');
        }

        return '/uploads/gallery/' . $filename;
    }

    public function deleteByPublicPath(?string $publicPath): void
    {
        if ($publicPath === null || $publicPath === '') {
            return;
        }

        if (!str_starts_with($publicPath, '/uploads/gallery/')) {
            return;
        }

        $filename = basename($publicPath);
        $file = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (is_file($file)) {
            unlink($file);
        }
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path) ?: '';
                finfo_close($finfo);
                if ($mime !== '') {
                    return $mime;
                }
            }
        }

        $imageInfo = @getimagesize($path);
        return is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой',
            UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE => 'Выберите файл',
            default => 'Ошибка загрузки файла',
        };
    }
}
