<?php

namespace App\Services;

use Illuminate\Support\Str;

class AreaTextResolver
{
    private const AREA_MAP = [
        'administracion' => [
            'label' => 'ADMINISTRACION Y FINANZAS',
            'id' => 1,
            'code' => 'AF',
        ],
        'gerencia' => [
            'label' => 'GERENCIA GENERAL',
            'id' => 6,
            'code' => 'GG',
        ],
        'sistemas' => [
            'label' => 'SISTEMAS',
            'id' => 7,
            'code' => 'SI',
        ],
        'legal' => [
            'label' => 'LEGAL',
            'id' => null,
            'code' => 'LG',
        ],
        'auditoria' => [
            'label' => 'AUDITORIA INTERNA',
            'id' => null,
            'code' => 'AU',
        ],
    ];

    public static function normalize(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->squish()
            ->value();
    }

    public static function resolveToken(?string $value): ?string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return null;
        }

        foreach (array_keys(self::AREA_MAP) as $token) {
            if (str_contains($normalized, $token)) {
                return $token;
            }
        }

        return $normalized;
    }

    public static function areasMatch(?string $a, ?string $b): bool
    {
        $tokenA = self::resolveToken($a);
        $tokenB = self::resolveToken($b);

        if ($tokenA !== null && $tokenB !== null) {
            return $tokenA === $tokenB;
        }

        return $tokenA !== null && $tokenB !== null && $tokenA === $tokenB;
    }

    public static function resolveLabel(?string $value): ?string
    {
        $token = self::resolveToken($value);
        if ($token === null) {
            return null;
        }

        return self::AREA_MAP[$token]['label'] ?? Str::of((string) $value)->ascii()->upper()->squish()->value();
    }

    public static function resolveCode(?string $value): string
    {
        $token = self::resolveToken($value);
        if ($token === null) {
            return 'XX';
        }

        return self::AREA_MAP[$token]['code'] ?? self::codeFromText($value);
    }

    public static function resolveId(?string $value): ?int
    {
        $token = self::resolveToken($value);
        if ($token === null) {
            return null;
        }

        return self::AREA_MAP[$token]['id'] ?? null;
    }

    public static function textFromId(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        foreach (self::AREA_MAP as $data) {
            if ($data['id'] === $id) {
                return $data['label'];
            }
        }

        return null;
    }

    private static function codeFromText(?string $value): string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return 'XX';
        }

        $words = preg_split('/\s+/', Str::of($normalized)->upper()->value());
        $code = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $code .= substr($word, 0, 1);
            if (strlen($code) >= 2) {
                break;
            }
        }

        return $code !== '' ? $code : 'XX';
    }
}
