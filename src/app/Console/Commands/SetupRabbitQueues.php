<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;

class SetupRabbitQueues extends Command
{
    protected $signature = 'rabbitmq:setup-rabbit-queues';

    protected $description = 'Create RabbitMQ queues';

    public function handle(): void
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_LOGIN', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest')
        );

        $channel = $connection->channel();

        $queues = [
            'notifications.high',
            'notifications.normal',
        ];

        foreach ($queues as $queue) {
            $channel->queue_declare(
                $queue,
                false,
                true,
                false,
                false
            );

            $this->info("Queue created: {$queue}");
        }

        Log::info('RabbitMQ queues created');

        $channel->close();
        $connection->close();
    }
}