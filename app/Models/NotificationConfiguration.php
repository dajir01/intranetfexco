<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationConfiguration extends Model
{
    protected $table = 'notification_configurations';

    protected $fillable = [
        'event_key',
        'name',
        'active',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(NotificationConfigurationArea::class, 'notification_configuration_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(NotificationConfigurationUser::class, 'notification_configuration_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(NotificationConfigurationEmail::class, 'notification_configuration_id');
    }
}
