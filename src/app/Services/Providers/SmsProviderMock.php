<?php

namespace App\Services\Providers;

use Illuminate\Support\Str;

class SmsProviderMock
{
    public function send(string $destination, string $text): array
    {
        // Имитация 50% ошибок для тестирования retry
        $shouldFail = rand(1, 100) <= 50;

        if ($shouldFail) {
            return [
                'success' => false,
                'error' => 'Provider temporarily unavailable',
                'provider_message_id' => null
            ];
        }

        return [
            'success' => true,
            'provider_message_id' => (string) Str::uuid(),
            'status' => 'delivered'
        ];
    }
}