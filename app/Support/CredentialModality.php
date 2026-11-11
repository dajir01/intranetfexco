<?php

namespace App\Support;

final class CredentialModality
{
    public const DIGITAL_ONLY_EVENT_ID = 19;

    public static function isDigitalOnlyEvent(int $idFeria): bool
    {
        return $idFeria === self::DIGITAL_ONLY_EVENT_ID;
    }

    public static function normalize(int $idFeria, ?int $modality): ?int
    {
        return self::isDigitalOnlyEvent($idFeria) ? 1 : $modality;
    }

    public static function label(int $idFeria, ?int $modality, bool $nullIsDigital = false): ?string
    {
        $modality = self::normalize($idFeria, $modality);

        if ($modality === null) {
            return $nullIsDigital ? 'Digital' : null;
        }

        return $modality === 1 ? 'Digital' : 'Física';
    }
}
