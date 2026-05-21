<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\IdempotencyKey;
use App\Models\Notification;
use App\Services\Providers\UserServiceMock;
use App\Services\Providers\SmsProviderMock;
use App\Services\Providers\EmailProviderMock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_successfully_creates_broadcast_enriches_data_and_dispatches_jobs()
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'channel' => 'sms',
            'text' => 'Ваш секретный код авторизации: 1234',
            'recipient_ids' => ['user_1', 'user_2']
        ];

        $response = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/broadcast', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure(['batch_id', 'status', 'priority']);
        $response->assertJsonPath('priority', 'high');

        $batchId = $response->json('batch_id');

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $idempotencyKey,
            'batch_id' => $batchId,
        ]);

        $mock = new UserServiceMock();
        $expectedContacts = $mock->getContactsByUserIds(['user_1', 'user_2']);

        foreach ($payload['recipient_ids'] as $recipientId) {
            $this->assertDatabaseHas('notifications', [
                'batch_id' => $batchId,
                'recipient_id' => $recipientId,
                'channel' => 'sms',
                'destination' => $expectedContacts[$recipientId]['phone'],
                'status' => 'queued',
                'priority' => 'high'
            ]);
        }

        $notifications = Notification::where('batch_id', $batchId)->get();

        foreach ($notifications as $notification) {
            Queue::assertPushedOn('notifications.high', SendNotificationJob::class, function ($job) use ($notification) {
                return $job->notificationId === $notification->id;
            });
        }
    }



    public function test_returns_existing_batch_on_duplicate_idempotency_key()
    {
        $this->withoutExceptionHandling();
        
        $idempotencyKey = 'unique-key-' . uniqid();

        $payload = [
            'channel' => 'email',
            'text' => 'Обычное уведомление',
            'recipient_ids' => ['user_3']
        ];
        
        $response1 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/broadcast', $payload);

        $batchId1 = $response1->json('batch_id');
        
        $response2 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/broadcast', $payload);

        $response2->assertStatus(200);
        $response2->assertJsonPath('batch_id', $batchId1);
        $response2->assertJsonPath('status', 'already_processed');
    }
    
    
    public function test_processes_job_correctly_and_prevents_double_sending()
    {
        $notification = new Notification();
        $notification->id = (string) Str::uuid();
        $notification->batch_id = (string) Str::uuid();
        $notification->recipient_id = 'user_test';
        $notification->destination = '+79991112233';
        $notification->channel = 'sms';
        $notification->text = 'Тест воркера';
        $notification->priority = 'normal';
        $notification->status = 'queued';
        $notification->save();

        $job = new SendNotificationJob($notification->id);
        
        $smsProviderMock = \Mockery::mock(SmsProviderMock::class);
        $smsProviderMock->shouldReceive('send')
            ->once()
            ->with('+79991112233', 'Тест воркера')
            ->andReturn(['success' => true, 'provider_message_id' => 'msg-123']);

        $emailProviderMock = \Mockery::mock(EmailProviderMock::class);
        
        $job->handle($smsProviderMock, $emailProviderMock);

        $notification->refresh();
        $this->assertEquals('delivered', $notification->status);
        $this->assertNotNull($notification->delivered_at);
        
        $smsProviderMock->shouldReceive('send')
            ->never();
        
        $job->handle($smsProviderMock, $emailProviderMock);

        $this->assertEquals('delivered', $notification->status);
    }
}