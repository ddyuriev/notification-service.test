<?php

namespace App\Services\Providers;

use Illuminate\Support\Str;

class EmailProviderMock
{
    public function send(string $destination, string $text): array
    {
        $shouldFail = rand(1, 100) <= 10;

        if ($shouldFail) {
            return [
                'success' => false,
                'error' => 'Email provider timeout',
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