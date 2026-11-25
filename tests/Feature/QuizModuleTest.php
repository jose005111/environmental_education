<?php

namespace Tests\Feature;

use App\Enums\QuizStatus;
use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_moderator_creates_quiz_with_nested_questions_and_options(): void
    {
        $moderator = $this->actingAsRole(RoleName::MODERADOR);
        $payload = $this->quizPayload(correctIndex: 1, optionCount: 3, questionCount: 2);

        $response = $this->postJson('/api/v1/quizzes', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', $payload['title'])
            ->assertJsonCount(2, 'data.questions');

        $quiz = Quiz::where('title', $payload['title'])->firstOrFail();

        $this->assertSame($moderator->id, $quiz->author_id);
        $this->assertSame(QuizStatus::PUBLICADO, $quiz->status);
        $this->assertCount(2, $quiz->questions);

        foreach ($quiz->questions as $question) {
            $this->assertCount(3, $question->options);
            $this->assertSame(1, $question->options->filter(fn (QuizOption $option) => $option->is_correct)->count());
            $this->assertTrue($question->options->firstWhere('position', 1)->is_correct);
        }

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertDatabaseCount('quizzes', 1);
        $this->assertDatabaseCount('quiz_questions', 2);
        $this->assertDatabaseCount('quiz_options', 6);
    }

    #[DataProvider('invalidQuizStructures')]
    public function test_create_rejects_invalid_question_invariants(string $case): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $payload = $this->quizPayload();

        switch ($case) {
            case 'nenhuma-correcta':
                foreach ($payload['questions'][0]['options'] as &$option) {
                    $option['is_correct'] = false;
                }
                unset($option);
                break;
            case 'duas-correctas':
                $payload['questions'][0]['options'][1]['is_correct'] = true;
                break;
            case 'uma-opcao':
                $payload['questions'][0]['options'] = array_slice($payload['questions'][0]['options'], 0, 1);
                break;
            case 'sete-opcoes':
                for ($index = 2; $index < 7; $index++) {
                    $payload['questions'][0]['options'][] = [
                        'text' => "Opção extra {$index}",
                        'is_correct' => false,
                        'position' => $index,
                    ];
                }
                break;
        }

        $this->postJson('/api/v1/quizzes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('questions.0.options');

        $this->assertDatabaseCount('quizzes', 0);
    }

    public static function invalidQuizStructures(): iterable
    {
        yield 'nenhuma opção correcta' => ['nenhuma-correcta'];
        yield 'duas opções correctas' => ['duas-correctas'];
        yield 'menos de duas opções' => ['uma-opcao'];
        yield 'mais de seis opções' => ['sete-opcoes'];
    }

    public function test_quiz_detail_never_exposes_correctness_or_explanation(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $quiz = Quiz::factory()->published()->withQuestions(2)->create();

        $response = $this->getJson("/api/v1/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.questions');

        foreach ($response->json('data.questions') as $question) {
            $this->assertArrayNotHasKey('explanation', $question);

            foreach ($question['options'] as $option) {
                $this->assertArrayNotHasKey('is_correct', $option);
            }
        }

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('explanation', $response->getContent());
    }

    public function test_resident_submits_attempt_and_receives_result_with_explanations(): void
    {
        $resident = $this->actingAsRole(RoleName::RESIDENTE);

        $quiz = Quiz::factory()->published()->withQuestions(2)->create();
        $questions = $quiz->questions()->with('options')->orderBy('position')->get()->values();

        $answers = $questions->map(function (QuizQuestion $question, int $index): array {
            $option = $index === 0
                ? $question->options->first(fn (QuizOption $item) => $item->is_correct)
                : $question->options->first(fn (QuizOption $item) => ! $item->is_correct);

            return ['question_id' => $question->id, 'option_id' => $option->id];
        })->all();

        $response = $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", ['answers' => $answers])
            ->assertCreated()
            ->assertJsonPath('data.total_questions', 2)
            ->assertJsonPath('data.correct_count', 1)
            ->assertJsonCount(2, 'data.answers');

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $resident->id)
            ->firstOrFail();

        $this->assertNotNull($attempt->completed_at);
        $this->assertSame(2, $attempt->total_questions);
        $this->assertSame(1, $attempt->correct_count);
        $this->assertCount(2, $attempt->answers);
        $this->assertSame(1, $attempt->answers->where('is_correct', true)->count());

        foreach ($response->json('data.answers') as $answer) {
            $this->assertArrayHasKey('is_correct', $answer);
            $this->assertArrayHasKey('explanation', $answer);
            $this->assertNotNull($answer['explanation']);
        }
    }

    public function test_attempt_accepts_a_raw_list_of_answers(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $quiz = Quiz::factory()->published()->withQuestions(1)->create();
        $question = $quiz->questions()->with('options')->firstOrFail();
        $option = $question->options->first(fn (QuizOption $item) => $item->is_correct);

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", [
            ['question_id' => $question->id, 'option_id' => $option->id],
        ])->assertCreated()
            ->assertJsonPath('data.correct_count', 1)
            ->assertJsonPath('data.total_questions', 1);
    }

    public function test_attempt_rejects_options_that_do_not_belong_to_the_question_or_quiz(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $quiz = Quiz::factory()->published()->withQuestions(2)->create();
        $otherQuiz = Quiz::factory()->published()->withQuestions(1)->create();

        [$first, $second] = $quiz->questions()->with('options')->get()->all();
        $foreignQuizOption = $otherQuiz->questions()->with('options')->firstOrFail()->options->firstOrFail();

        $answers = [
            ['question_id' => $first->id, 'option_id' => $first->options->firstOrFail()->id],
            ['question_id' => $second->id, 'option_id' => $foreignQuizOption->id],
        ];

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", ['answers' => $answers])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers.1.option_id');

        $answers[1]['option_id'] = 999999;

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", ['answers' => $answers])
            ->assertUnprocessable();

        $answers[1] = [
            'question_id' => $foreignQuizOption->quiz_question_id,
            'option_id' => $foreignQuizOption->id,
        ];

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", ['answers' => $answers])
            ->assertUnprocessable();

        $this->assertDatabaseCount('quiz_attempts', 0);
        $this->assertDatabaseCount('quiz_attempt_answers', 0);
    }

    public function test_attempt_requires_exactly_one_answer_per_question(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $quiz = Quiz::factory()->published()->withQuestions(2)->create();
        [$first, $second] = $quiz->questions()->with('options')->get()->all();

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", ['answers' => [
            ['question_id' => $first->id, 'option_id' => $first->options->firstOrFail()->id],
        ]])->assertUnprocessable();

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", ['answers' => [
            ['question_id' => $first->id, 'option_id' => $first->options->firstOrFail()->id],
            ['question_id' => $first->id, 'option_id' => $first->options->last()->id],
        ]])->assertUnprocessable();

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", ['answers' => [
            ['question_id' => $first->id, 'option_id' => $first->options->firstOrFail()->id],
            ['question_id' => $second->id, 'option_id' => $first->options->last()->id],
        ]])->assertUnprocessable();

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_quiz_with_answers_cannot_be_deleted(): void
    {
        $this->actingAsRole(RoleName::ADMINISTRADOR);

        $quiz = Quiz::factory()->published()->withQuestions(1)->create();
        QuizAttempt::factory()->for($quiz)->withAnswers()->create();

        $this->deleteJson("/api/v1/quizzes/{$quiz->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'conflict');

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);

        $emptyQuiz = Quiz::factory()->published()->withQuestions(1)->create();

        $this->deleteJson("/api/v1/quizzes/{$emptyQuiz->id}")->assertNoContent();

        $this->assertDatabaseMissing('quizzes', ['id' => $emptyQuiz->id]);
    }

    public function test_update_cannot_remove_questions_or_options_with_answers(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $quiz = Quiz::factory()->published()->withQuestions(2)->create();
        [$answered, $kept] = $quiz->questions()->with('options')->get()->all();
        QuizAttempt::factory()->for($quiz)->withAnswers()->create();

        $this->putJson("/api/v1/quizzes/{$quiz->id}", [
            'title' => 'Questionário actualizado',
            'questions' => [$this->questionPayload($kept)],
        ])->assertConflict();

        $this->assertDatabaseHas('quiz_questions', ['id' => $answered->id]);

        $answeredOptions = $answered->options->values()->all();
        $keptOptions = $kept->options->values()->all();
        $answeredUpdate = $this->questionPayload($answered, array_slice($answeredOptions, 1));
        $answeredUpdate['options'][0]['is_correct'] = true;

        $this->putJson("/api/v1/quizzes/{$quiz->id}", [
            'title' => 'Questionário actualizado',
            'questions' => [
                $answeredUpdate,
                $this->questionPayload($kept, $keptOptions),
            ],
        ])->assertConflict();

        $this->assertDatabaseHas('quiz_options', ['id' => $answeredOptions[1]->id]);
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
    }

    public function test_moderator_updates_quiz_and_its_questions(): void
    {
        $this->actingAsRole(RoleName::MODERADOR);

        $quiz = Quiz::factory()->published()->withQuestions(1)->create();
        $question = $quiz->questions()->with('options')->firstOrFail();

        $this->putJson("/api/v1/quizzes/{$quiz->id}", [
            'title' => 'Questionário revisto',
            'questions' => [$this->questionPayload($question, $question->options->values()->all())],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Questionário revisto');

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'title' => 'Questionário revisto']);
        $this->assertDatabaseCount('quiz_questions', 1);
        $this->assertDatabaseCount('quiz_options', 4);
    }

    public function test_index_only_lists_published_quizzes_for_residents(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $published = Quiz::factory()->published()->withQuestions(1)->create();
        Quiz::factory()->withQuestions(1)->create();

        $this->getJson('/api/v1/quizzes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('meta.total', 1);

        $this->actingAsRole(RoleName::MODERADOR);

        $this->getJson('/api/v1/quizzes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/quizzes')->assertUnauthorized();
        $this->getJson('/api/v1/me/attempts')->assertUnauthorized();
        $this->postJson('/api/v1/quizzes', $this->quizPayload())->assertUnauthorized();
    }

    public function test_residents_cannot_manage_quizzes_or_see_drafts(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $published = Quiz::factory()->published()->withQuestions(1)->create();
        $draft = Quiz::factory()->withQuestions(1)->create();

        $this->postJson('/api/v1/quizzes', $this->quizPayload())->assertForbidden();
        $this->putJson("/api/v1/quizzes/{$published->id}", ['title' => 'Alterado'])->assertForbidden();
        $this->deleteJson("/api/v1/quizzes/{$published->id}")->assertForbidden();
        $this->getJson("/api/v1/quizzes/{$draft->id}")->assertForbidden();
    }

    public function test_missing_quizzes_return_not_found(): void
    {
        $this->actingAsRole(RoleName::RESIDENTE);

        $this->getJson('/api/v1/quizzes/999999')->assertNotFound();

        $this->postJson('/api/v1/quizzes/999999/attempts', ['answers' => [
            ['question_id' => 1, 'option_id' => 1],
        ]])->assertNotFound();
    }

    public function test_history_lists_only_the_authenticated_resident_attempts(): void
    {
        $resident = $this->actingAsRole(RoleName::RESIDENTE);

        $quiz = Quiz::factory()->published()->withQuestions(1)->create();
        $mine = QuizAttempt::factory()->for($quiz)->for($resident)->withAnswers()->create();
        QuizAttempt::factory()->for($quiz)->withAnswers()->create();

        $this->getJson('/api/v1/me/attempts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.quiz.id', $quiz->id)
            ->assertJsonPath('meta.total', 1);
    }

    private function quizPayload(int $correctIndex = 0, int $optionCount = 2, int $questionCount = 1): array
    {
        $questions = [];

        for ($questionIndex = 0; $questionIndex < $questionCount; $questionIndex++) {
            $options = [];

            for ($optionIndex = 0; $optionIndex < $optionCount; $optionIndex++) {
                $options[] = [
                    'text' => "Opção {$optionIndex} da pergunta {$questionIndex}",
                    'is_correct' => $optionIndex === $correctIndex,
                    'position' => $optionIndex,
                ];
            }

            $questions[] = [
                'statement' => "Qual é a resposta {$questionIndex}?",
                'explanation' => "Explicação {$questionIndex}",
                'position' => $questionIndex,
                'options' => $options,
            ];
        }

        return [
            'category_id' => Category::factory()->create()->id,
            'title' => 'Questionário de reciclagem',
            'description' => 'Perguntas sobre reciclagem.',
            'status' => QuizStatus::PUBLICADO->value,
            'questions' => $questions,
        ];
    }

    private function questionPayload(QuizQuestion $question, ?array $options = null): array
    {
        return [
            'id' => $question->id,
            'statement' => $question->statement,
            'explanation' => $question->explanation,
            'position' => $question->position,
            'options' => collect($options ?? $question->options)->map(fn (QuizOption $option) => [
                'id' => $option->id,
                'text' => $option->text,
                'is_correct' => $option->is_correct,
                'position' => $option->position,
            ])->all(),
        ];
    }
}
