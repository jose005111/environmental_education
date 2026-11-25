<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ContentType;
use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Content;
use App\Models\Event;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Report;
use App\Models\ReportUpdate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_enums_have_portuguese_labels(): void
    {
        $this->assertSame('Artigo', ContentType::ARTIGO->label());
        $this->assertSame('Em análise', ReportStatus::EM_ANALISE->label());
        $this->assertSame('Concluído', EventStatus::CONCLUIDO->label());
        $this->assertSame('Administrador', RoleName::ADMINISTRADOR->label());
    }

    public function test_slugs_are_generated_and_never_reused(): void
    {
        $categoryId = Category::factory()->create(['name' => 'Categoria base'])->id;

        $first = Content::factory()->create(['title' => 'Guia de reciclagem', 'category_id' => $categoryId]);
        $second = Content::factory()->create(['title' => 'Guia de reciclagem', 'category_id' => $categoryId]);

        $this->assertSame('guia-de-reciclagem', $first->slug);
        $this->assertSame('guia-de-reciclagem-2', $second->slug);

        $first->delete();
        $replacement = Content::factory()->create(['title' => 'Guia de reciclagem', 'category_id' => $categoryId]);

        $this->assertSame('guia-de-reciclagem-3', $replacement->slug);
        $this->assertSame('reciclagem', Category::factory()->create(['name' => 'Reciclagem'])->slug);
    }

    public function test_user_role_helpers_use_the_seeded_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertTrue(User::factory()->admin()->create()->isAdmin());
        $this->assertTrue(User::factory()->moderator()->create()->isModerator());
        $this->assertTrue(User::factory()->create()->isResident());
    }

    public function test_quiz_relations_and_answer_history(): void
    {
        $quiz = Quiz::factory()->published()->withQuestions(2)->create();

        $this->assertCount(2, $quiz->questions);

        foreach ($quiz->questions as $question) {
            $this->assertCount(4, $question->options);
            $this->assertSame(1, $question->options->filter(fn ($option) => $option->is_correct)->count());
        }

        $resident = User::factory()->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($resident)->withAnswers()->create();

        $this->assertCount(2, $attempt->answers);
        $this->assertTrue($attempt->user->is($resident));
        $this->assertTrue($resident->quizAttempts()->whereKey($attempt->id)->exists());
    }

    public function test_activities_and_events_keep_their_many_to_many_history(): void
    {
        $activity = Activity::factory()->open()->withParticipations(2)->create();

        $this->assertSame(ActivityStatus::ABERTA, $activity->status);
        $this->assertCount(2, $activity->participants);
        $this->assertTrue($activity->participants->first()->participatedActivities()->whereKey($activity->id)->exists());

        $event = Event::factory()->published()->withRegistrations(2)->create();

        $this->assertSame(EventStatus::PUBLICADO, $event->status);
        $this->assertCount(2, $event->registrants);
        $this->assertTrue($event->registrants->first()->registeredEvents()->whereKey($event->id)->exists());
    }

    public function test_reports_keep_photos_and_status_history(): void
    {
        $report = Report::factory()->resolved()->withPhotos()->create();

        $this->assertTrue(Report::public()->whereKey($report->id)->exists());
        $this->assertCount(2, $report->photos);
        $this->assertTrue($report->zone->reports()->whereKey($report->id)->exists());

        $update = ReportUpdate::factory()
            ->for($report)
            ->transition(ReportStatus::RECEBIDO, ReportStatus::EM_ANALISE, 'Em análise')
            ->create();

        $this->assertSame(ReportStatus::EM_ANALISE, $update->to_status);
        $this->assertSame(ReportStatus::RECEBIDO, $update->from_status);
    }

    public function test_demo_seeder_creates_a_complete_neighbourhood(): void
    {
        $this->seed();

        $this->assertSame(3, Role::count());
        $this->assertSame(5, Category::count());
        $this->assertTrue(User::where('email', 'admin@ambiente.local')->firstOrFail()->isAdmin());
        $this->assertTrue(Content::published()->exists());
        $this->assertTrue(Quiz::where('status', 'publicado')->exists());
        $this->assertTrue(Report::public()->exists());
        $this->assertTrue(Activity::where('status', 'aberta')->exists());
        $this->assertTrue(Event::published()->exists());
    }
}
