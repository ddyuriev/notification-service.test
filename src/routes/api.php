<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

// Mass broadcast
Route::post('/v1/broadcast', [NotificationController::class, 'broadcast']);

// Subscriber statuses
Route::get('/v1/subscribers/{recipientId}/notifications', [StatusController::class, 'getSubscriberStatuses']);