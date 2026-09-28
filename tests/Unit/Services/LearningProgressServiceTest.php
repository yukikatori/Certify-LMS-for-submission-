<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_section_chapter_part_progress(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $partA = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $partB = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA1 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA2 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterB1 = Chapter::factory()
            ->for($partB)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_1 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_2 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA2_1 = Section::factory()
            ->for($chapterA2)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1_1 = Section::factory()
            ->for($chapterB1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1_2 = Section::factory()
            ->for($chapterB1)
            ->create(['status' => ContentStatus::Published->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA1_1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA1_2)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA2_1)
            ->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(5, $summary->sectionsTotal);
        $this->assertSame(3, $summary->sectionsCompleted);
        $this->assertSame(0.6, $summary->sectionCompletionRatio);
        $this->assertSame(0.6, $summary->overallCompletionRatio);

        $this->assertSame(3, $summary->chaptersTotal);
        $this->assertSame(2, $summary->chaptersCompleted);
        $this->assertSame(0.6667, $summary->chapterCompletionRatio);

        $this->assertSame(2, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(0.5, $summary->partCompletionRatio);
    }

    public function test_summarize_ignores_unpublished_content(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $partA = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $partB = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Draft->value]);

        $chapterA1 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA2 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterB1 = Chapter::factory()
            ->for($partB)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_1 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_2 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA2_1 = Section::factory()
            ->for($chapterA2)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1_1 = Section::factory()
            ->for($chapterB1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1_2 = Section::factory()
            ->for($chapterB1)
            ->create(['status' => ContentStatus::Published->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA1_1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA1_2)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionB1_1)
            ->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(0.6667, $summary->sectionCompletionRatio);
        $this->assertSame(0.6667, $summary->overallCompletionRatio);

        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.5, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);
    }

    public function test_summarize_isolated_by_enrollment(): void
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment1 = Enrollment::factory()
            ->for($student1)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $enrollment2 = Enrollment::factory()
            ->for($student2)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $partA = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $partB = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Draft->value]);

        $chapterA1 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA2 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterB1 = Chapter::factory()
            ->for($partB)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_1 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_2 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA2_1 = Section::factory()
            ->for($chapterA2)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1_1 = Section::factory()
            ->for($chapterB1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1_2 = Section::factory()
            ->for($chapterB1)
            ->create(['status' => ContentStatus::Published->value]);

        // $enrollment1の進捗
        SectionProgress::factory()
            ->forEnrollment($enrollment1)
            ->forSection($sectionA1_1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment1)
            ->forSection($sectionA1_2)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment1)
            ->forSection($sectionB1_1)
            ->create();

        // enrollment2 の進捗。
        // enrollment1 では未読の sectionA2_1 も読了にして、混入したら検知できるようにする
        SectionProgress::factory()
            ->forEnrollment($enrollment2)
            ->forSection($sectionA1_1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment2)
            ->forSection($sectionA1_2)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment2)
            ->forSection($sectionA2_1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment2)
            ->forSection($sectionB1_1)
            ->create();

        // $enrollment1のみ集計
        $summary = app(LearningProgressService::class)->summarize($enrollment1);

        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(0.6667, $summary->sectionCompletionRatio);
        $this->assertSame(0.6667, $summary->overallCompletionRatio);

        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.5, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);
    }

    public function test_batch_section_completion_ratios_returns_ratios_by_enrollment(): void
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();

        $certificationA = Certification::factory()->published()->create();
        $certificationB = Certification::factory()->published()->create();

        $enrollment1 = Enrollment::factory()
            ->for($student1)
            ->for($certificationA)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $enrollment2 = Enrollment::factory()
            ->for($student2)
            ->for($certificationB)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $partA = Part::factory()
            ->for($certificationA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1 = Section::factory()
            ->for($chapterA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA2 = Section::factory()
            ->for($chapterA)
            ->create(['status' => ContentStatus::Published->value]);

        $partB = Part::factory()
            ->for($certificationB)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterB = Chapter::factory()
            ->for($partB)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB1 = Section::factory()
            ->for($chapterB)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB2 = Section::factory()
            ->for($chapterB)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionB3 = Section::factory()
            ->for($chapterB)
            ->create(['status' => ContentStatus::Published->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollment1)
            ->forSection($sectionA1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment2)
            ->forSection($sectionB1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment2)
            ->forSection($sectionB2)
            ->create();

        $ratios = app(LearningProgressService::class)
            ->batchSectionCompletionRatios(collect([$enrollment1, $enrollment2]));

        $this->assertSame(0.5, $ratios[(string) $enrollment1->id]);
        $this->assertSame(0.6667, $ratios[(string) $enrollment2->id]);
    }

    public function test_completed_section_counts_by_chapter_returns_counts(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $partA = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA1 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $chapterA2 = Chapter::factory()
            ->for($partA)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_1 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA1_2 = Section::factory()
            ->for($chapterA1)
            ->create(['status' => ContentStatus::Published->value]);

        $sectionA2_1 = Section::factory()
            ->for($chapterA2)
            ->create(['status' => ContentStatus::Published->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA1_1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA1_2)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($sectionA2_1)
            ->create();

        $results = app(LearningProgressService::class)
            ->completedSectionCountsByChapter(
                $enrollment,
                collect([$chapterA1, $chapterA2]),
            );

        $this->assertSame(2, $results[$chapterA1->id]);
        $this->assertSame(1, $results[$chapterA2->id]);
    }

    public function test_summarize_returns_zero_ratios_when_no_published_sections(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $part = Part::factory()
            ->for($certification)
            ->create(['status' => ContentStatus::Published->value]);

        $chapter = Chapter::factory()
            ->for($part)
            ->create(['status' => ContentStatus::Published->value]);

        $draftSection = Section::factory()
            ->for($chapter)
            ->create(['status' => ContentStatus::Draft->value]);

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($draftSection)
            ->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
        $this->assertSame(0.0, $summary->overallCompletionRatio);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);
    }
}
