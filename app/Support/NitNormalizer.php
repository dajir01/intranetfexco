<?php

namespace App\Support;

class NitNormalizer
{
    public static function normalize(string $nit): string
    {
        return preg_replace('/[^0-9]/', '', $nit) ?? '';
    }
}
