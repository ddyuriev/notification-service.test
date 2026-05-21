<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->string('recipient_id');
            $table->string('channel'); // sms, email
            $table->string('destination'); // recipient sms or email
            $table->text('text');
            $table->string('priority'); // high, normal
            $table->string('status'); // queued, sent, delivered, dropped
            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'status']);
            $table->index('batch_id');
            $table->index(['priority', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
