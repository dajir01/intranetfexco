<?php

namespace App\Jobs;

use App\Services\MailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCentralizedMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $eventName;
    public array $payload;
    public array $recipients;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 60, 120];

    public function __construct(string $eventName, array $payload = [], array $recipients = [])
    {
        $this->eventName = $eventName;
        $this->payload = $payload;
        $this->recipients = $recipients;
    }

    public function handle(MailNotificationService $mailNotificationService): void
    {
        try {
            $mailNotificationService->send($this->eventName, $this->payload, $this->recipients);
        } catch (\Throwable $e) {
            Log::error('Error enviando correo centralizado', [
                'event' => $this->eventName,
                'recipients' => $this->recipients,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
