<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationConfigurationEmail extends Model
{
    protected $table = 'notification_configuration_emails';

    protected $fillable = [
        'notification_configuration_id',
        'email',
        'type',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(NotificationConfiguration::class, 'notification_configuration_id');
    }
}
