<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Quizzes\CreateQuiz;
use App\Actions\Quizzes\DeleteQuiz;
use App\Actions\Quizzes\QuizConflictException;
use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Actions\Quizzes\UpdateQuiz;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\StoreQuizRequest;
use App\Http\Requests\Api\V1\SubmitQuizAttemptRequest;
use App\Http\Requests\Api\V1\UpdateQuizRequest;
use App\Http\Resources\QuizAttemptResource;
use App\Http\Resources\QuizResource;
use App\Http\Resources\QuizSummaryResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Support\QueryFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;

class QuizController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Quiz::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'status' => ['column' => 'status', 'operator' => '='],
                'category_id' => ['column' => 'category_id', 'operator' => '='],
                'category' => ['column' => 'category_id', 'operator' => '='],
            ],
            sorts: [
                'title' => 'title',
                'status' => 'status',
                'created_at' => 'created_at',
            ],
            search: ['title', 'description'],
        );

        $query = Quiz::query()->with('category')->withCount('questions');
        $user = $request->user();

        if (! $user->isModerator() && ! $user->isAdmin()) {
            $query->published();
        }

        $quizzes = $filter->apply($query)->paginate($filter->perPage());

        return $this->paginated($quizzes, QuizSummaryResource::class, $request);
    }

    public function show(Quiz $quiz): QuizResource
    {
        Gate::authorize('view', $quiz);

        return new QuizResource($quiz->load('category', 'questions.options'));
    }

    public function store(StoreQuizRequest $request): JsonResponse
    {
        Gate::authorize('create', Quiz::class);

        $quiz = app(CreateQuiz::class)->execute($request->validated(), $request->user());

        return $this->created(new QuizResource($quiz), 'Questionário criado com sucesso.');
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('update', $quiz);

        try {
            $quiz = app(UpdateQuiz::class)->execute($quiz, $request->validated());
        } catch (QuizConflictException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return $this->success('Questionário actualizado com sucesso.', new QuizResource($quiz));
    }

    public function destroy(Quiz $quiz): HttpResponse|JsonResponse
    {
        Gate::authorize('delete', $quiz);

        try {
            app(DeleteQuiz::class)->execute($quiz);
        } catch (QuizConflictException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return $this->noContent();
    }

    public function attempt(SubmitQuizAttemptRequest $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('attempt', $quiz);

        $data = $request->validated();
        $attempt = app(SubmitQuizAttempt::class)->execute($quiz, $request->user(), $data['answers']);

        return $this->created(new QuizAttemptResource($attempt), 'Tentativa submetida com sucesso.');
    }

    public function history(IndexRequest $request): JsonResponse
    {
        $filter = new QueryFilter(
            $request,
            filters: [
                'quiz_id' => ['column' => 'quiz_id', 'operator' => '='],
            ],
            sorts: [
                'created_at' => 'created_at',
                'completed_at' => 'completed_at',
            ],
            search: [],
        );

        $query = QuizAttempt::query()
            ->where('user_id', $request->user()->id)
            ->with('quiz', 'answers.question', 'answers.option');

        $attempts = $filter->apply($query)->paginate($filter->perPage());

        return $this->paginated($attempts, QuizAttemptResource::class, $request);
    }

    public function attempts(IndexRequest $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('update', $quiz);

        $filter = new QueryFilter(
            $request,
            sorts: ['created_at' => 'created_at', 'completed_at' => 'completed_at'],
            search: [],
            defaultSort: '-completed_at',
        );

        $attempts = $filter->apply(
            QuizAttempt::query()->where('quiz_id', $quiz->id)->with('user')
        )->paginate($filter->perPage());

        return $this->paginated($attempts, QuizAttemptResource::class, $request);
    }
}
