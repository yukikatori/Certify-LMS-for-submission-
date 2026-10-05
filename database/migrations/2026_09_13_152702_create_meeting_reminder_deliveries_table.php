<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談リマインダーの送信済み履歴テーブル。
 * 同じ面談・同じ受信者・同じリマインダー種別の二重送信を防ぐために使用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_reminder_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignUlid('meeting_id')
                ->constrained('meetings')
                ->cascadeOnDelete();

            $table->foreignUlid('recipient_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('window', 32);

            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['meeting_id', 'recipient_user_id', 'window'],
                'meeting_reminder_delivery_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reminder_deliveries');
    }
};
