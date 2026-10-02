<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationConfigurationArea extends Model
{
    protected $table = 'notification_configuration_areas';

    protected $fillable = [
        'notification_configuration_id',
        'area',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(NotificationConfiguration::class, 'notification_configuration_id');
    }
}
