<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 同一コーチ・同一時刻枠の面談予約を DB レベルで 1 件に制限する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unique(
                ['coach_id', 'scheduled_at'],
                'meetings_coach_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique('meetings_coach_slot_unique');
        });
    }
};
