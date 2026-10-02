<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationConfigurationUser extends Model
{
    protected $table = 'notification_configuration_users';

    protected $fillable = [
        'notification_configuration_id',
        'user_id',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(NotificationConfiguration::class, 'notification_configuration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'id_usuario');
    }
}
