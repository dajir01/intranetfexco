<?php

namespace App\Http\Controllers;

use App\Models\NotificationConfiguration;
use App\Models\NotificationConfigurationArea;
use App\Models\NotificationConfigurationEmail;
use App\Models\NotificationConfigurationUser;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationConfigurationController extends Controller
{
    public function index(): JsonResponse
    {
        $configurations = NotificationConfiguration::query()
            ->with(['areas', 'users.user', 'emails'])
            ->orderBy('event_key')
            ->get()
            ->map(fn (NotificationConfiguration $configuration) => $this->serializeConfiguration($configuration))
            ->values();

        return response()->json([
            'data' => $configurations,
        ]);
    }

    public function show(string $eventKey): JsonResponse
    {
        $configuration = NotificationConfiguration::query()
            ->with(['areas', 'users.user', 'emails'])
            ->where('event_key', $eventKey)
            ->first();

        if (!$configuration) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $this->serializeConfiguration($configuration),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_key' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:150'],
            'active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'areas' => ['nullable', 'array'],
            'areas.*' => ['string', 'max:120'],
            'users' => ['nullable', 'array'],
            'users.*' => ['integer', 'min:1'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email'],
        ]);

        $data = $this->persistConfiguration($validated);

        return response()->json([
            'message' => 'Configuración guardada correctamente',
            'data' => $this->serializeConfiguration($data),
        ], 201);
    }

    public function update(Request $request, string $eventKey): JsonResponse
    {
        $configuration = NotificationConfiguration::query()
            ->where('event_key', $eventKey)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'areas' => ['nullable', 'array'],
            'areas.*' => ['string', 'max:120'],
            'users' => ['nullable', 'array'],
            'users.*' => ['integer', 'min:1'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email'],
        ]);

        $configuration->fill([
            'name' => $validated['name'],
            'active' => $validated['active'] ?? $configuration->active,
            'description' => $validated['description'] ?? $configuration->description,
        ]);
        $configuration->save();

        $this->syncRelations($configuration, $validated);

        $configuration->load(['areas', 'users.user', 'emails']);

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'data' => $this->serializeConfiguration($configuration),
        ]);
    }

    public function toggle(string $eventKey): JsonResponse
    {
        $configuration = NotificationConfiguration::query()
            ->where('event_key', $eventKey)
            ->firstOrFail();

        $configuration->active = !$configuration->active;
        $configuration->save();

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'data' => $this->serializeConfiguration($configuration->fresh(['areas', 'users.user', 'emails'])),
        ]);
    }

    public function areas(): JsonResponse
    {
        $areas = Usuario::query()
            ->select('area')
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->distinct()
            ->orderBy('area')
            ->pluck('area')
            ->filter()
            ->map(fn ($area) => trim((string) $area))
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'data' => $areas,
        ]);
    }

    public function users(): JsonResponse
    {
        $users = Usuario::query()
            ->select(['id_usuario', 'nombre_usuario', 'email', 'area', 'estado'])
            ->orderBy('nombre_usuario')
            ->get()
            ->map(function (Usuario $usuario) {
                return [
                    'id_usuario' => (int) $usuario->id_usuario,
                    'nombre_usuario' => $usuario->nombre_usuario,
                    'email' => $usuario->email,
                    'area' => $usuario->area,
                    'estado' => $usuario->estado,
                ];
            })
            ->values();

        return response()->json([
            'data' => $users,
        ]);
    }

    protected function persistConfiguration(array $validated): NotificationConfiguration
    {
        return DB::transaction(function () use ($validated): NotificationConfiguration {
            $eventKey = strtolower(str_replace([' ', '-', '::'], '_', trim((string) $validated['event_key'])));
            $configuration = NotificationConfiguration::query()->firstOrNew(['event_key' => $eventKey]);
            $configuration->fill([
                'name' => $validated['name'],
                'active' => (bool) ($validated['active'] ?? true),
                'description' => $validated['description'] ?? null,
            ]);
            $configuration->save();

            $this->syncRelations($configuration, $validated);

            $configuration->load(['areas', 'users.user', 'emails']);

            return $configuration;
        });
    }

    protected function syncRelations(NotificationConfiguration $configuration, array $validated): void
    {
        $areas = array_values(array_unique(array_map(fn ($area) => trim((string) $area), $validated['areas'] ?? [])));
        $userIds = array_values(array_unique(array_map('intval', $validated['users'] ?? [])));

        $configuration->areas()->delete();
        foreach ($areas as $area) {
            if ($area === '') {
                continue;
            }

            $configuration->areas()->create(['area' => strtoupper($area)]);
        }

        $configuration->users()->delete();
        foreach ($userIds as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $configuration->users()->create(['user_id' => $userId]);
        }

        $configuration->emails()->delete();
        foreach (['cc', 'bcc'] as $type) {
            foreach (($validated[$type] ?? []) as $email) {
                $email = trim((string) $email);
                if ($email === '') {
                    continue;
                }

                $configuration->emails()->create([
                    'email' => $email,
                    'type' => $type,
                ]);
            }
        }
    }

    protected function serializeConfiguration(NotificationConfiguration $configuration): array
    {
        return [
            'id' => $configuration->id,
            'event_key' => $configuration->event_key,
            'name' => $configuration->name,
            'active' => (bool) $configuration->active,
            'description' => $configuration->description,
            'areas' => $configuration->areas->pluck('area')->map(fn ($area) => trim((string) $area))->values()->all(),
            'users' => $configuration->users
                ->map(fn ($item) => [
                    'user_id' => (int) ($item->user_id ?? $item->user?->id_usuario ?? 0),
                    'id_usuario' => (int) ($item->user_id ?? $item->user?->id_usuario ?? 0),
                    'nombre_usuario' => $item->user?->nombre_usuario,
                    'email' => $item->user?->email,
                    'area' => $item->user?->area,
                ])
                ->filter(fn ($user) => (int) ($user['user_id'] ?? 0) > 0)
                ->values()
                ->all(),
            'cc' => $configuration->emails->where('type', 'cc')->pluck('email')->values()->all(),
            'bcc' => $configuration->emails->where('type', 'bcc')->pluck('email')->values()->all(),
        ];
    }
}
