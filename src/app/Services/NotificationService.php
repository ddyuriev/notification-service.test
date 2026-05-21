<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\IdempotencyKey;
use App\Jobs\SendNotificationJob;
use App\Services\Providers\UserServiceMock;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class NotificationService
{
    protected UserServiceMock $userService;
    
    public function __construct(UserServiceMock $userService)
    {
        $this->userService = $userService;
    }

    public function createBroadcast(string $channel, string $text, array $recipientIds, string $priority, string $idempotencyKey): array
    {
//        try {
//            $idempotency = IdempotencyKey::create([
//                'key' => $idempotencyKey,
//                'batch_id' => (string) Str::uuid(),
//                'status' => 'processing'
//            ]);
//        } catch (QueryException $e) {
//            // Если поймали unique constraint — ключ уже существует
//            $existing = IdempotencyKey::where('key', $idempotencyKey)->first();
//            return [
//                'batch_id' => $existing->batch_id,
//                'status' => 'already_processed',
//                'is_new' => false
//            ];
//        }



/**/
        // 1. Сначала ПРОВЕРЯЕМ наличие ключа через безопасный SELECT
        $existing = IdempotencyKey::where('key', $idempotencyKey)->first();

        if ($existing) {
            return [
                'batch_id' => $existing->batch_id,
                'status' => 'already_processed',
                'is_new' => false
            ];
        }

        // 2. Если ключа нет — спокойно создаем. try-catch вокруг этого INSERT больше не нужен!
        $idempotency = IdempotencyKey::create([
            'key' => $idempotencyKey,
            'batch_id' => (string) Str::uuid(),
            'status' => 'processing'
        ]);
/**/
        $batchId = $idempotency->batch_id;
        
        $contacts = $this->userService->getContactsByUserIds($recipientIds);

        $notificationsData = [];
        $now = now();
        
        foreach ($recipientIds as $recipientId) {
            $userContact = $contacts[$recipientId] ?? null;
            
            $destination = ($channel === 'sms')
                ? ($userContact['phone'] ?? null)
                : ($userContact['email'] ?? null);

            $notificationsData[] = [
                'id' => (string) Str::uuid(),
                'batch_id' => $batchId,
                'recipient_id' => $recipientId,
                'channel' => $channel,
                'destination' => $destination,
                'text' => $text,
                'priority' => $priority,
                'status' => $destination ? 'queued' : 'dropped',
                'last_error' => $destination ? null : 'Contact details not found in User Service',
                'retry_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::beginTransaction();
        try {
            Notification::insert($notificationsData);

            $idempotency->update(['status' => 'completed']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $idempotency->delete(); // Освобождаем ключ при жесткой ошибке БД
            throw $e;
        }
        
        $queue = ($priority === 'high') ? 'notifications.high' : 'notifications.normal';
        foreach ($notificationsData as $data) {
            // В RabbitMQ пушим только те сообщения, для которых нашлись контакты
            if ($data['status'] === 'queued') {
                SendNotificationJob::dispatch($data['id'])
                    ->onConnection('rabbitmq')
                    ->onQueue($queue);
            }
        }

        return [
            'batch_id' => $batchId,
            'status' => 'accepted',
            'is_new' => true
        ];
    }

    public function getSubscriberStatuses(string $recipientId)
    {
        return Notification::where('recipient_id', $recipientId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }
}