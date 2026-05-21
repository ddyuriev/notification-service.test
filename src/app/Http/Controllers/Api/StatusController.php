<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function getSubscriberStatuses(Request $request, string $recipientId): JsonResponse
    {
        $statuses = $this->notificationService->getSubscriberStatuses($recipientId);

        return response()->json([
            'recipient_id' => $recipientId,
            'notifications' => $statuses->items(),
            'pagination' => [
                'current_page' => $statuses->currentPage(),
                'per_page' => $statuses->perPage(),
                'total' => $statuses->total(),
                'last_page' => $statuses->lastPage()
            ]
        ]);
    }
}