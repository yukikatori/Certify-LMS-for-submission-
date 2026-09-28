<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 受講生の学習進捗を算出する。
 */
final class LearningProgressService
{
    public function summarize(Enrollment $enrollment): ProgressSummary
    {
        $totals = $this->fetchSectionTotals($enrollment);

        $partsTotal = Part::query()
            ->where('certification_id', $enrollment->certification_id)
            ->where('status', ContentStatus::Published->value)
            ->count();

        $chaptersTotal = Chapter::query()
            ->whereHas('part', function ($q) use ($enrollment) {
                $q->where('certification_id', $enrollment->certification_id)
                    ->where('status', ContentStatus::Published->value);
            })
            ->where('status', ContentStatus::Published->value)
            ->count();

        $sectionsTotal = (int) $totals->sections_total;
        $sectionsCompleted = (int) $totals->sections_completed;
        $sectionRatio = $sectionsTotal === 0 ? 0.0 : round($sectionsCompleted / $sectionsTotal, 4);

        $chaptersCompleted = $this->countCompletedChapters($enrollment);
        $partsCompleted = $this->countCompletedParts($enrollment);

        return new ProgressSummary(
            sectionsTotal: $sectionsTotal,
            sectionsCompleted: $sectionsCompleted,
            sectionCompletionRatio: $sectionRatio,
            chaptersTotal: $chaptersTotal,
            chaptersCompleted: $chaptersCompleted,
            chapterCompletionRatio: $chaptersTotal === 0 ? 0.0 : round($chaptersCompleted / $chaptersTotal, 4),
            partsTotal: $partsTotal,
            partsCompleted: $partsCompleted,
            partCompletionRatio: $partsTotal === 0 ? 0.0 : round($partsCompleted / $partsTotal, 4),
            overallCompletionRatio: $sectionRatio,
        );
    }

    /**
     * @param EloquentCollection<int, Enrollment>|Collection<int, Enrollment> $enrollments
     *
     * @return array<string, float>
     */
    public function batchSectionCompletionRatios($enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $enrollmentIds = $enrollments->pluck('id')->all();
        $certificationIds = $enrollments->pluck('certification_id')->unique()->values()->all();

        $rows = DB::table('sections')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->join('enrollments', 'enrollments.certification_id', '=', 'parts.certification_id')
            ->leftJoin('section_progresses', function ($join): void {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->on('section_progresses.enrollment_id', '=', 'enrollments.id');
            })
            ->whereIn('enrollments.id', $enrollmentIds)
            ->whereIn('parts.certification_id', $certificationIds)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->where('sections.status', ContentStatus::Published->value)
            ->groupBy('enrollments.id')
            ->selectRaw('enrollments.id AS enrollment_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get();

        $result = [];
        foreach ($enrollmentIds as $id) {
            $result[(string) $id] = 0.0;
        }

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $done = (int) $row->done;

            $result[(string) $row->enrollment_id] = $total === 0
                ? 0.0
                : round($done / $total, 4);
        }

        return $result;
    }

    /**
     * @param Collection<int, Chapter>|EloquentCollection<int, Chapter> $chapters
     *
     * @return array<string, int>
     */
    public function completedSectionCountsByChapter(Enrollment $enrollment, $chapters): array
    {
        if ($chapters->isEmpty()) {
            return [];
        }

        return DB::table('sections')
            ->join('section_progresses', function ($join) use ($enrollment): void {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->whereIn('sections.chapter_id', $chapters->pluck('id'))
            ->where('sections.status', ContentStatus::Published->value)
            ->groupBy('sections.chapter_id')
            ->selectRaw('sections.chapter_id AS chapter_id, COUNT(*) AS done')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->chapter_id => (int) $row->done])
            ->all();
    }

    private function fetchSectionTotals(Enrollment $enrollment): object
    {
        return DB::table('sections')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment): void {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->where('sections.status', ContentStatus::Published->value)
            ->selectRaw('COUNT(sections.id) AS sections_total, COUNT(section_progresses.id) AS sections_completed')
            ->first() ?? (object) ['sections_total' => 0, 'sections_completed' => 0];
    }

    private function countCompletedChapters(Enrollment $enrollment): int
    {
        return DB::table('chapters')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('sections', function ($join): void {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment): void {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->groupBy('chapters.id')
            ->selectRaw('chapters.id AS chapter_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get()
            ->filter(fn ($row): bool => (int) $row->total > 0 && (int) $row->total === (int) $row->done)
            ->count();
    }

    private function countCompletedParts(Enrollment $enrollment): int
    {
        return DB::table('parts')
            ->leftJoin('chapters', function ($join): void {
                $join->on('chapters.part_id', '=', 'parts.id')
                    ->where('chapters.status', ContentStatus::Published->value);
            })
            ->leftJoin('sections', function ($join): void {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment): void {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->groupBy('parts.id')
            ->selectRaw('parts.id AS part_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get()
            ->filter(fn ($row): bool => (int) $row->total > 0 && (int) $row->total === (int) $row->done)
            ->count();
    }
}
