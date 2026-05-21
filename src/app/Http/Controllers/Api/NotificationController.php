<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,email',
            'text' => 'required|string|max:1000',
            'recipient_ids' => 'required|array|max:1000',
            'recipient_ids.*' => 'required|string'
        ]);

        // Проверка Idempotency-Key
        $idempotencyKey = $request->header('Idempotency-Key');
        if (!$idempotencyKey) {
            return response()->json([
                'error' => 'Idempotency-Key header is required'
            ], 400);
        }

        // Определяем приоритет
        $priority = $this->determinePriority($validated['text']);

        // Создаём рассылку
        $result = $this->notificationService->createBroadcast(
            $validated['channel'],
            $validated['text'],
            $validated['recipient_ids'],
            $priority,
            $idempotencyKey
        );

        $statusCode = $result['is_new'] ? 201 : 200;

        return response()->json([
            'batch_id' => $result['batch_id'],
            'status' => $result['status'],
            'priority' => $priority
        ], $statusCode);
    }

    private function determinePriority(string $text): string
    {
        $highPriorityKeywords = ['код', 'доступ', 'срочно', 'пароль', 'code', 'urgent', 'password'];
        foreach ($highPriorityKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return 'high';
            }
        }
        return 'normal';
    }
}