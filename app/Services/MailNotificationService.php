<?php

namespace App\Services;

use App\Jobs\SendCentralizedMailJob;
use App\Mail\Contrato\ContratoCreadoMail;
use App\Mail\Contrato\ActualizacionDatosMail;
use App\Mail\Pago\PagoRegistradoMail;
use App\Mail\Reserva\ReservaCreadaMail;
use App\Models\NotificationConfiguration;
use App\Models\Usuario;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailNotificationService
{
    protected array $eventAreas = [
        'reserva_creada' => ['COMERCIAL'],
        'contrato_creado' => ['COMERCIAL', 'FINANZAS'],
        'pago_registrado' => ['FINANZAS'],
        'actualizacion_datos' => ['SISTEMAS'],
    ];

    protected array $eventPermissions = [
        'reserva_creada' => ['contratos.view', 'contratos.create', 'contratos.edit', 'contratos.update'],
        'contrato_creado' => ['contratos.view', 'contratos.create', 'contratos.edit', 'contratos.update'],
        'pago_registrado' => ['pagos.view', 'pagos.create', 'pagos.approve', 'pagos.export'],
        'actualizacion_datos' => ['contratos.view', 'contratos.edit', 'contratos.update'],
    ];

    public function queueEvent(string $eventName, array $payload = [], array $metadata = []): void
    {
        $normalized = $this->normalizeEventName($eventName);
        $recipients = $this->resolveRecipients($normalized);

        if (empty($recipients)) {
            Log::info('No se encontraron destinatarios para el evento de correo', [
                'event' => $normalized,
                'payload' => $payload,
            ]);

            return;
        }

        $fingerprint = $this->buildFingerprint($normalized, $payload);

        if ($this->wasAlreadySent($fingerprint)) {
            return;
        }

        $this->markAsSent($fingerprint);

        dispatch(new SendCentralizedMailJob($normalized, $payload, $recipients));
    }

    public function sendImmediately(string $eventName, array $payload = []): void
    {
        $normalized = $this->normalizeEventName($eventName);
        $recipients = $this->resolveRecipients($normalized);

        if (empty($recipients)) {
            Log::info('No se encontraron destinatarios para el evento de correo', [
                'event' => $normalized,
                'payload' => $payload,
            ]);

            return;
        }

        $fingerprint = $this->buildFingerprint($normalized, $payload);

        if ($this->wasAlreadySent($fingerprint)) {
            return;
        }

        $this->send($normalized, $payload, $recipients);
        $this->markAsSent($fingerprint);
    }

    public function resolveRecipients(string $eventName): array
    {
        $normalized = $this->normalizeEventName($eventName);

        $configuration = NotificationConfiguration::query()
            ->where('event_key', $normalized)
            ->with(['areas', 'users.user'])
            ->first();

        if ($configuration) {
            if (!$configuration->active) {
                return [];
            }

            $emails = collect();

            if ($configuration->areas->isNotEmpty()) {
                $areas = $configuration->areas->pluck('area')->filter()->map(fn ($area) => mb_strtoupper(trim((string) $area)))->all();

                $areaUsers = Usuario::query()
                    ->where(function ($query) use ($areas) {
                        foreach ($areas as $area) {
                            $query->orWhereRaw('UPPER(TRIM(COALESCE(area, ""))) = ?', [$area]);
                        }
                    })
                    ->where(function ($query) {
                        $query->where('estado', 1)->orWhereNull('estado');
                    })
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->whereRaw('TRIM(email) <> ""')
                    ->get();

                $emails = $emails->merge(
                    $this->filterByGranularPermissions($areaUsers, $normalized)->pluck('email')
                );
            }

            if ($configuration->users->isNotEmpty()) {
                $userIds = $configuration->users->pluck('user_id')->filter()->all();

                $emails = $emails->merge(
                    Usuario::query()->whereIn('id_usuario', $userIds)->pluck('email')
                );
            }

            return $this->sanitizeEmailList($emails->all());
        }

        $areas = $this->eventAreas[$normalized] ?? [];
        if (empty($areas)) {
            return [];
        }

        $areaUsers = Usuario::query()
            ->where(function ($query) use ($areas) {
                foreach ($areas as $area) {
                    $query->orWhereRaw('UPPER(TRIM(COALESCE(area, ""))) = ?', [mb_strtoupper(trim((string) $area))]);
                }
            })
            ->where(function ($query) {
                $query->where('estado', 1)->orWhereNull('estado');
            })
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw('TRIM(email) <> ""')
            ->orderByRaw('CASE WHEN UPPER(TRIM(COALESCE(area, ""))) = "COMERCIAL" THEN 1 WHEN UPPER(TRIM(COALESCE(area, ""))) = "FINANZAS" THEN 2 ELSE 3 END ASC')
            ->orderBy('nombre_usuario')
            ->get();

        $emails = $this->filterByGranularPermissions($areaUsers, $normalized)
            ->pluck('email');

        return $this->sanitizeEmailList($emails->all());
    }

    public function resolveCc(string $eventName): array
    {
        return $this->resolveEmailListByType($eventName, 'cc');
    }

    public function resolveBcc(string $eventName): array
    {
        return $this->resolveEmailListByType($eventName, 'bcc');
    }

    protected function resolveEmailListByType(string $eventName, string $type): array
    {
        $normalized = $this->normalizeEventName($eventName);
        $configuration = NotificationConfiguration::query()
            ->where('event_key', $normalized)
            ->with(['emails'])
            ->first();

        if (!$configuration || !$configuration->active) {
            return [];
        }

        return $this->sanitizeEmailList(
            $configuration->emails
                ->where('type', $type)
                ->pluck('email')
                ->all()
        );
    }

    protected function filterByGranularPermissions(\Illuminate\Support\Collection $users, string $eventName): \Illuminate\Support\Collection
    {
        $permissions = $this->eventPermissions[$eventName] ?? [];

        if (empty($permissions)) {
            return $users;
        }

        return $users->filter(function (Usuario $user) use ($permissions): bool {
            foreach ($permissions as $permission) {
                if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
                    return true;
                }
            }

            return false;
        });
    }

    protected function sanitizeEmailList(array $emails): array
    {
        $normalized = collect($emails)
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        return array_values($normalized);
    }

    public function send(string $eventName, array $payload = [], array $recipients = []): void
    {
        $normalized = $this->normalizeEventName($eventName);
        $resolvedRecipients = $recipients ?: $this->resolveRecipients($normalized);

        if (empty($resolvedRecipients)) {
            Log::warning('No hay destinatarios válidos para enviar el correo.', [
                'event' => $normalized,
                'payload' => $payload,
            ]);

            return;
        }

        $mailable = match ($normalized) {
            'reserva_creada' => new ReservaCreadaMail($payload),
            'contrato_creado' => new ContratoCreadoMail($payload),
            'pago_registrado' => new PagoRegistradoMail($payload),
            'actualizacion_datos' => new ActualizacionDatosMail($payload),
            default => null,
        };

        if ($mailable === null) {
            Log::warning('Evento no soportado para correo centralizado.', [
                'event' => $normalized,
            ]);

            return;
        }

        $mailer = Mail::to($resolvedRecipients);
        $cc = $this->resolveCc($normalized);
        $bcc = $this->resolveBcc($normalized);

        if (!empty($cc)) {
            $mailer->cc($cc);
        }

        if (!empty($bcc)) {
            $mailer->bcc($bcc);
        }

        $mailer->send($mailable);
    }

    protected function normalizeEventName(string $eventName): string
    {
        return strtolower(str_replace([' ', '-', '::'], '_', trim($eventName)));
    }

    protected function buildFingerprint(string $eventName, array $payload): string
    {
        $body = json_encode([
            'event' => $eventName,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'central-mail:' . md5((string) $body);
    }

    protected function wasAlreadySent(string $fingerprint): bool
    {
        return Cache::has($fingerprint);
    }

    protected function markAsSent(string $fingerprint): void
    {
        Cache::put($fingerprint, true, now()->addMinutes(30));
    }
}
