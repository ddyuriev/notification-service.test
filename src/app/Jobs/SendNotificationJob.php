<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Providers\SmsProviderMock;
use App\Services\Providers\EmailProviderMock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [1, 4, 16];
    public $notificationId;

    public function __construct(string $notificationId)
    {
        $this->notificationId = $notificationId;
    }
    
    public function handle(SmsProviderMock $smsProvider, EmailProviderMock $emailProvider)
    {
        $notification = Notification::find($this->notificationId);

        if (!$notification) {
            return;
        }

        // 1. ПЕРВАЯ ФАЗА: Быстрая проверка статуса перед любыми действиями
        if (in_array($notification->status, ['processing', 'sent', 'delivered', 'dropped'])) {
            return; // Защита от дубликатов из очереди RabbitMQ
        }

        // 2. ВТОРАЯ ФАЗА: Атомарный захват задачи (State Lock)
        // Обновляем статус только если он всё еще равен 'queued' или 'failed_retry'
        $updated = Notification::where('id', $this->notificationId)
            ->whereIn('status', ['queued', 'failed_retry'])
            ->update(['status' => 'processing']);

        // Если $updated === 0, значит другой параллельный воркер уже перехватил эту запись
        if (!$updated) {
            return;
        }

        // Перечитываем свежие данные из базы (нам нужен актуальный destination и текст)
        $notification->refresh();

        $destination = $notification->destination;
        if (!$destination) {
            $notification->update([
                'status' => 'dropped',
                'last_error' => 'Destination address is missing. Check enrichment stage.'
            ]);
            return;
        }

        $provider = ($notification->channel === 'sms') ? $smsProvider : $emailProvider;

        try {
            // 3. ТРЕТЬЯ ФАЗА: Внешнее действие (Выполняется строго один раз)
            $response = $provider->send($destination, $notification->text);

            // 4. ЧЕТВЕРТАЯ ФАЗА: Фиксация финального результата
            $notification->update([
                'provider_response' => $response,
                'status' => $response['success'] ? 'delivered' : 'dropped',
                'delivered_at' => $response['success'] ? now() : null,
                'last_error' => $response['success'] ? null : ($response['error'] ?? 'Unknown provider error')
            ]);

            // Если провайдер вернул ошибку, кидаем исключение для запуска ретраев (backoff)
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Provider error');
            }

        } catch (\Throwable $e) {
            // В случае сетевой или системной ошибки откатываем статус, разрешая ретрай
            $notification->update([
                'status' => 'failed_retry',
                'last_error' => $e->getMessage(),
                'retry_count' => $this->attempts()
            ]);

            throw $e; // Пробрасываем исключение, чтобы Laravel вернул джобу в RabbitMQ с задержкой
        }
    }

    public function failed(\Throwable $e)
    {
        $notification = Notification::find($this->notificationId);
        if ($notification) {
            $notification->update([
                'status' => 'dropped',
                'last_error' => 'Max retry attempts reached. Last error: ' . $e->getMessage()
            ]);
        }
    }
}