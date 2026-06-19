<?php

declare(strict_types=1);

namespace App\Services;

final class SlugService
{
    /** @var array<string, string> */
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = '';

        foreach ($chars as $char) {
            if (isset(self::TRANSLIT[$char])) {
                $result .= self::TRANSLIT[$char];
                continue;
            }

            if (preg_match('/[a-z0-9]/', $char)) {
                $result .= $char;
                continue;
            }

            $result .= '-';
        }

        $slug = preg_replace('/-+/', '-', $result) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'post';
    }

    public function unique(string $base, callable $exists): string
    {
        $slug = $this->slugify($base);
        if (!$exists($slug)) {
            return $slug;
        }

        $i = 2;
        while ($exists($slug . '-' . $i)) {
            $i++;
        }

        return $slug . '-' . $i;
    }
}
