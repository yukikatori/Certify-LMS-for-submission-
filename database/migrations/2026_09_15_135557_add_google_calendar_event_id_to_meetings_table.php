<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meetings テーブルに Google Calendar event id を追加する。
 *
 * LMS から作成した Google Calendar イベントを、面談キャンセル時に正確に削除するために保持する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('google_calendar_event_id')
                ->nullable()
                ->after('meeting_url_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('google_calendar_event_id');
        });
    }
};
