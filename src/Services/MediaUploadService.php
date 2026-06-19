<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class MediaUploadService
{
    /** @param array<string, string> $mimeToExt */
    public function __construct(
        private readonly string $uploadDir,
        private readonly string $publicUrlPrefix,
        private readonly array $mimeToExt,
        private readonly int $maxBytes = 5_242_880,
    ) {
    }

    public static function blogCover(string $projectRoot): self
    {
        return new self(
            $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . 'images',
            '/uploads/blog/images/',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'],
        );
    }

    public static function blogVideo(string $projectRoot): self
    {
        return new self(
            $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . 'videos',
            '/uploads/blog/videos/',
            ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
            52_428_800,
        );
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

        if ((int) $file['size'] > $this->maxBytes) {
            $mb = (int) round($this->maxBytes / 1024 / 1024);
            throw new InvalidArgumentException("Файл слишком большой (макс. {$mb} МБ)");
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Некорректная загрузка файла');
        }

        $mime = $this->detectMime($tmp);
        if (!isset($this->mimeToExt[$mime])) {
            throw new InvalidArgumentException('Недопустимый тип файла');
        }

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
            throw new \RuntimeException('Не удалось создать папку для загрузок');
        }

        $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $this->mimeToExt[$mime];
        $target = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Не удалось сохранить файл');
        }

        return rtrim($this->publicUrlPrefix, '/') . '/' . $filename;
    }

    public function deleteByPublicPath(?string $publicPath): void
    {
        if ($publicPath === null || $publicPath === '') {
            return;
        }

        $prefix = rtrim($this->publicUrlPrefix, '/') . '/';
        if (!str_starts_with($publicPath, $prefix)) {
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
        if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
            return (string) $imageInfo['mime'];
        }

        return '';
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
