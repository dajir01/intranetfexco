<?php

namespace App\Services;

use DateTimeImmutable;
use RuntimeException;

class LaravelLogViewer
{
    private const FILE_PATTERN = '/^laravel(?:-\d{4}-\d{2}-\d{2})?\.log$/';

    private const ENTRY_PATTERN = '/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(?<environment>[^.\s]+)\.(?<level>[A-Z]+):\s*(?<message>.*)$/';

    public function files(): array
    {
        $directory = storage_path('logs');
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (glob($directory.'/*.log') ?: [] as $path) {
            $name = basename($path);
            if (! preg_match(self::FILE_PATTERN, $name) || ! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'size' => filesize($path) ?: 0,
                'updated_at' => (new DateTimeImmutable('@'.filemtime($path)))->format('Y-m-d H:i:s'),
            ];
        }

        usort($files, fn (array $left, array $right): int => strcmp($right['name'], $left['name']));

        return $files;
    }

    public function entries(string $filename, array $filters = []): array
    {
        $path = $this->resolveFile($filename);
        $records = $this->parseTail($path);
        $level = strtolower((string) ($filters['level'] ?? ''));
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        $records = array_values(array_filter($records, function (array $record) use ($level, $search): bool {
            if ($level !== '' && $record['level'] !== $level) {
                return false;
            }

            return $search === '' || str_contains(mb_strtolower($record['message']), $search);
        }));

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $total = count($records);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($records, $offset, $perPage);

        return [
            'data' => $items,
            'meta' => [
                'filename' => basename($path),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'truncated' => $this->isTruncated($path),
            ],
        ];
    }

    private function resolveFile(string $filename): string
    {
        abort_unless(preg_match(self::FILE_PATTERN, $filename), 404, 'Archivo de registro no disponible.');

        $path = storage_path('logs/'.$filename);
        $directory = realpath(storage_path('logs'));
        $resolved = realpath($path);

        abort_unless($directory !== false && $resolved !== false && is_file($resolved) && str_starts_with(dirname($resolved).DIRECTORY_SEPARATOR, $directory.DIRECTORY_SEPARATOR), 404, 'Archivo de registro no disponible.');
        abort_unless(is_readable($resolved), 403, 'El archivo de registro no es legible.');

        return $resolved;
    }

    private function parseTail(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No fue posible abrir el archivo de registro.');
        }

        $size = filesize($path) ?: 0;
        $readLimit = (int) (env('LOG_VIEW_MAX_BYTES', 8 * 1024 * 1024));
        $start = max(0, $size - max(1024, $readLimit));
        if ($start > 0) {
            fseek($handle, $start);
            fgets($handle);
        }

        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $lines[] = rtrim($line, "\r\n");
        }
        fclose($handle);

        $records = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match(self::ENTRY_PATTERN, $line, $match)) {
                if ($current !== null) {
                    $records[] = $this->serializeRecord($current);
                }
                $current = [
                    'timestamp' => $match['timestamp'],
                    'environment' => $match['environment'],
                    'level' => strtolower($match['level']),
                    'message' => $match['message'],
                    'context' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['context'][] = $line;
            }
        }
        if ($current !== null) {
            $records[] = $this->serializeRecord($current);
        }

        return array_reverse($records);
    }

    private function serializeRecord(array $record): array
    {
        $details = array_values(array_filter($record['context'], fn (string $line): bool => trim($line) !== ''));

        return [
            'timestamp' => $record['timestamp'],
            'environment' => $record['environment'],
            'level' => $record['level'],
            'message' => $record['message'],
            'details' => implode("\n", $details),
        ];
    }

    private function isTruncated(string $path): bool
    {
        $size = filesize($path) ?: 0;
        $readLimit = (int) (env('LOG_VIEW_MAX_BYTES', 8 * 1024 * 1024));

        return $size > max(1024, $readLimit);
    }
}
