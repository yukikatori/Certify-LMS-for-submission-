<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * 面談リマインダー通知（前日・1時間前）の Schedule Command。
 */
class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders
        {--window= : eve または one_hour_before}';
    
    protected $description = '予約済み面談の前日または開始1時間前リマインダー通知を送信する';

    public function handle(SendMeetingReminderAction $action): int
    {
        $window = (string) $this->option('window');

        if (! in_array($window, ['eve', 'one_hour_before'], true)) {
            $this->error('--window は eve または one_hour_before を指定してください。');

            return self::FAILURE;
        }

        $meetings = match ($window) {
            'eve' => $this->eveMeetings(),
            'one_hour_before' => $this->oneHourBeforeMeetings(),
        };

        $count = 0;

        $meetings->chunkById(100, function ($meetings) use ($action, $window, &$count): void {
            foreach ($meetings as $meeting) {
                $action($meeting, $window);
                $count++;
            }
        });

        $this->info("面談リマインダー対象 {$count} 件を処理しました。");

        return self::SUCCESS;
    }

    private function eveMeetings(): Builder
    {
        return Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->whereBetween('scheduled_at', [
                now()->addDay()->startOfDay(),
                now()->addDay()->endOfDay(),
            ])
            ->with(['student', 'coach', 'enrollment.certification']);
    }

    private function oneHourBeforeMeetings(): Builder
    {
        $start = now()->addHour()->startOfMinute();
        $end = $start->copy()->addMinute();

        return Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->where('scheduled_at', '>=', $start)
            ->where('scheduled_at', '<', $end)
            ->with(['student', 'coach', 'enrollment.certification']);
    }
}
