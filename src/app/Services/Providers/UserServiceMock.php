<?php

namespace App\Services\Providers;

use Faker\Factory;

class UserServiceMock
{
    public function getContactsByUserIds(array $userIds): array
    {
        $result = [];

        foreach ($userIds as $id) {
            $seed = crc32($id);
            mt_srand($seed);

            $result[$id] = [
                'phone' => '+7999' . str_pad(abs($seed) % 10000000, 7, '0', STR_PAD_LEFT),
                'email' => "user_" . substr($id, 0, 5) . "@example.com"
            ];
        }

        return $result;
    }
}