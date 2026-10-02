<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nombre_usuario',
        'nick_usuario',
        'pass_usuario',
        'nivel_usuario',
        'area',
        'jefatura',
        'email',
        'creado_por',
        'estado',
    ];

    protected $hidden = [
        'pass_usuario',
        'remember_token',
    ];

    public function getAuthIdentifierName()
    {
        return 'nick_usuario';
    }

    public function getAuthPassword()
    {
        return $this->pass_usuario;
    }

    public function setPassUsuarioAttribute($value)
    {
        $value = (string) ($value ?? '');
        $this->attributes['pass_usuario'] = password_get_info($value)['algo'] !== null
            ? $value
            : Hash::make($value);
    }

    /**
     * Verifica si el usuario está activo.
     * Un usuario está activo si:
     * - Tiene estado = 1, O
     * - El campo estado es null (compatibilidad hacia atrás)
     */
    public function isActive(): bool
    {
        // Si no hay estado definido, considerar activo (compatibilidad)
        if ($this->estado === null) {
            return true;
        }

        return (int)$this->estado === 1;
    }

    /**
     * Verifica si el usuario está inactivo.
     */
    public function isInactive(): bool
    {
        return !$this->isActive();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'user_permissions',
            'user_id',
            'permission_id',
            'id_usuario',
            'id'
        )->withTimestamps();
    }

    public function hasGranularPermissions(): bool
    {
        if (!self::granularTablesReady()) {
            return false;
        }

        if ($this->relationLoaded('permissions')) {
            return $this->permissions->isNotEmpty();
        }

        return $this->permissions()->exists();
    }

    public function hasPermission(string $permission): bool
    {
        $normalized = self::normalizePermissionName($permission);
        if ($normalized === '') {
            return false;
        }

        if (!self::granularTablesReady()) {
            return false;
        }

        $aliases = self::permissionAliases($normalized);

        if ($this->relationLoaded('permissions')) {
            $grantedPermissions = $this->permissions->pluck('name')->all();
            $grantedIndex = array_flip(array_map([self::class, 'normalizePermissionName'], $grantedPermissions));

            foreach ($aliases as $alias) {
                if (isset($grantedIndex[$alias])) {
                    return true;
                }
            }

            return false;
        }

        return $this->permissions()
            ->whereIn('name', $aliases)
            ->exists();
    }

    protected static function permissionAliases(string $permission): array
    {
        $permission = self::normalizePermissionName($permission);
        $legacyToGranular = config('acl.legacy_to_granular', []);
        $granularToLegacy = array_flip($legacyToGranular);

        $aliases = [$permission];

        if (isset($legacyToGranular[$permission])) {
            $aliases[] = self::normalizePermissionName((string) $legacyToGranular[$permission]);
        }

        if (isset($granularToLegacy[$permission])) {
            $aliases[] = self::normalizePermissionName((string) $granularToLegacy[$permission]);
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    protected static function normalizePermissionName(?string $permission): string
    {
        return mb_strtolower(trim((string) $permission));
    }

    protected static function granularTablesReady(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('permissions') && Schema::hasTable('user_permissions');

        return $ready;
    }
}

