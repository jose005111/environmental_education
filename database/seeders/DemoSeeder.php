<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Enums\EventStatus;
use App\Enums\ParticipationStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Category;
use App\Models\Content;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Report;
use App\Models\ReportPhoto;
use App\Models\ReportUpdate;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $moderator = $this->user('moderador@ambiente.local', 'Marina Moderadora', RoleName::MODERADOR);
        $resident = $this->user('joao@ambiente.local', 'João Residente', RoleName::RESIDENTE);
        $otherResident = $this->user('ana@ambiente.local', 'Ana Residente', RoleName::RESIDENTE);

        $lixo = Category::where('slug', 'lixo')->firstOrFail();
        $reciclagem = Category::where('slug', 'reciclagem')->firstOrFail();
        $saneamento = Category::where('slug', 'saneamento')->firstOrFail();
        $centro = Zone::where('name', 'Centro')->firstOrFail();
        $sul = Zone::where('name', 'Zona Sul')->firstOrFail();

        $this->contents($moderator, $lixo, $reciclagem);
        $quiz = $this->quiz($reciclagem, $moderator);
        $this->attempt($quiz, $resident);
        $this->reports($resident, $moderator, $centro, $lixo, $saneamento);
        $this->activities($moderator, $resident, $otherResident, $lixo);
        $this->events($moderator, $resident, $otherResident, $centro, $sul, $reciclagem);
    }

    private function user(string $email, string $name, RoleName $roleName): User
    {
        $role = Role::where('name', $roleName->value)->firstOrFail();

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role_id' => $role->id,
                'zone_id' => Zone::where('name', 'Centro')->value('id'),
                'phone' => '(21) 99999-0001',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
    }

    private function contents(User $author, Category $lixo, Category $reciclagem): void
    {
        $items = [
            [
                'category' => $lixo,
                'title' => 'Como separar o lixo em casa',
                'summary' => 'Um guia simples para separar resíduos recicláveis e orgânicos.',
                'body' => 'Separe papel, plástico, vidro e metal em sacos distintos. Os resíduos orgânicos podem ir para a compostagem.',
                'type' => ContentType::ARTIGO,
                'status' => ContentStatus::PUBLICADO,
            ],
            [
                'category' => $reciclagem,
                'title' => 'Cinco dicas para reciclar melhor',
                'summary' => 'Pequenas mudanças que melhoram a qualidade do material reciclável.',
                'body' => 'Limpe as embalagens, retire tampas e evite misturar materiais diferentes.',
                'type' => ContentType::DICA,
                'status' => ContentStatus::PUBLICADO,
            ],
            [
                'category' => $lixo,
                'title' => 'Coleta seletiva: como participar',
                'summary' => 'Saiba onde deixar os materiais recicláveis no bairro.',
                'body' => 'Leve os materiais até os pontos de coleta da praça central e da orla.',
                'type' => ContentType::INFOGRAFICO,
                'status' => ContentStatus::RASCUNHO,
            ],
        ];

        foreach ($items as $item) {
            $status = $item['status'];

            Content::firstOrCreate(
                ['slug' => Str::slug($item['title'])],
                [
                    'category_id' => $item['category']->id,
                    'author_id' => $author->id,
                    'title' => $item['title'],
                    'summary' => $item['summary'],
                    'body' => $item['body'],
                    'type' => $item['type'],
                    'status' => $status,
                    'published_at' => $status === ContentStatus::PUBLICADO ? now()->subDays(5) : null,
                    'views_count' => 24,
                ],
            );
        }
    }

    private function quiz(Category $category, User $author): Quiz
    {
        $quiz = Quiz::firstOrCreate(
            ['title' => 'Reciclagem: o que separar?'],
            [
                'category_id' => $category->id,
                'author_id' => $author->id,
                'description' => 'Teste seus conhecimentos sobre separação de resíduos.',
                'status' => 'publicado',
            ],
        );

        if ($quiz->questions()->exists()) {
            return $quiz;
        }

        $questions = [
            [
                'statement' => 'Qual destes materiais pode ser reciclado?',
                'explanation' => 'Papel, plástico, vidro e metal são materiais recicláveis.',
                'options' => [
                    ['text' => 'Papel limpo e seco', 'is_correct' => true],
                    ['text' => 'Restos de comida', 'is_correct' => false],
                    ['text' => 'Fraldas descartáveis', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'O que fazer com uma garrafa de vidro usada?',
                'explanation' => 'A garrafa deve ser limpa e levada ao ponto de coleta de vidro.',
                'options' => [
                    ['text' => 'Jogar no lixo comum', 'is_correct' => false],
                    ['text' => 'Levar ao ponto de coleta de vidro', 'is_correct' => true],
                    ['text' => 'Quebrar e deixar na calçada', 'is_correct' => false],
                ],
            ],
        ];

        foreach ($questions as $position => $question) {
            $model = QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'statement' => $question['statement'],
                'explanation' => $question['explanation'],
                'position' => $position + 1,
            ]);

            foreach ($question['options'] as $optionPosition => $option) {
                QuizOption::create([
                    'quiz_question_id' => $model->id,
                    'text' => $option['text'],
                    'is_correct' => $option['is_correct'],
                    'position' => $optionPosition + 1,
                ]);
            }
        }

        return $quiz;
    }

    private function attempt(Quiz $quiz, User $resident): void
    {
        if ($quiz->attempts()->where('user_id', $resident->id)->exists()) {
            return;
        }

        QuizAttempt::factory()
            ->for($quiz)
            ->for($resident)
            ->withAnswers()
            ->create();
    }

    private function reports(
        User $resident,
        User $moderator,
        Zone $centro,
        Category $lixo,
        Category $saneamento,
    ): void {
        $items = [
            [
                'title' => 'Acúmulo de lixo na praça central',
                'description' => 'Sacos de lixo abandonados próximos ao coreto há mais de dois dias.',
                'status' => ReportStatus::RESOLVIDO,
                'is_public' => true,
                'zone' => $centro,
                'category' => $lixo,
                'note' => 'Coleta extraordinária realizada pela prefeitura.',
            ],
            [
                'title' => 'Bueiro entupido na rua das Palmeiras',
                'description' => 'Água acumulada na calçada após as chuvas da última semana.',
                'status' => ReportStatus::EM_ANALISE,
                'is_public' => false,
                'zone' => $centro,
                'category' => $saneamento,
                'note' => 'Equipe de drenagem acionada.',
            ],
        ];

        foreach ($items as $item) {
            $status = $item['status'];

            $report = Report::firstOrCreate(
                ['title' => $item['title']],
                [
                    'user_id' => $resident->id,
                    'zone_id' => $item['zone']->id,
                    'category_id' => $item['category']->id,
                    'assigned_to' => $moderator->id,
                    'description' => $item['description'],
                    'location_note' => 'Próximo ao número 100',
                    'latitude' => -22.9068,
                    'longitude' => -43.1729,
                    'status' => $status,
                    'is_public' => $item['is_public'],
                    'resolved_at' => $status === ReportStatus::RESOLVIDO ? now()->subDay() : null,
                ],
            );

            if ($report->photos()->count() === 0) {
                ReportPhoto::create(['report_id' => $report->id, 'path' => 'reports/exemplo-1.jpg']);
                ReportPhoto::create(['report_id' => $report->id, 'path' => 'reports/exemplo-2.jpg']);
            }

            if ($report->updates()->exists()) {
                continue;
            }

            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $resident->id,
                'from_status' => null,
                'to_status' => ReportStatus::RECEBIDO,
                'note' => 'Reporte enviado pelo residente.',
            ]);

            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $moderator->id,
                'from_status' => ReportStatus::RECEBIDO,
                'to_status' => ReportStatus::EM_ANALISE,
                'note' => $item['note'],
            ]);

            if ($status === ReportStatus::RESOLVIDO) {
                ReportUpdate::create([
                    'report_id' => $report->id,
                    'user_id' => $moderator->id,
                    'from_status' => ReportStatus::EM_ANALISE,
                    'to_status' => ReportStatus::RESOLVIDO,
                    'note' => 'Coleta concluída e local limpo.',
                ]);
            }
        }
    }

    private function activities(
        User $moderator,
        User $resident,
        User $otherResident,
        Category $category,
    ): void {
        $items = [
            [
                'title' => 'Coleta seletiva na praça',
                'summary' => 'Separe e leve seus recicláveis até a praça central.',
                'instructions' => 'Separe papel, plástico e vidro. Leve os materiais até a tenda da praça e registre sua participação.',
                'status' => ActivityStatus::ABERTA,
                'starts_on' => today()->subDay(),
                'ends_on' => today()->addDays(7),
            ],
            [
                'title' => 'Mutirão de limpeza da orla',
                'summary' => 'Mutirão para limpar a orla do bairro.',
                'instructions' => 'Use luvas e saquinhos. Concentre o lixo coletado nos pontos indicados.',
                'status' => ActivityStatus::ENCERRADA,
                'starts_on' => today()->subDays(14),
                'ends_on' => today()->subDays(7),
            ],
        ];

        foreach ($items as $item) {
            $status = $item['status'];
            $title = $item['title'];

            $activity = Activity::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'category_id' => $category->id,
                    'author_id' => $moderator->id,
                    'title' => $title,
                    'summary' => $item['summary'],
                    'instructions' => $item['instructions'],
                    'starts_on' => $item['starts_on'],
                    'ends_on' => $item['ends_on'],
                    'status' => $status,
                ],
            );

            if ($activity->participations()->exists()) {
                continue;
            }

            ActivityParticipation::factory()
                ->for($activity)
                ->for($resident)
                ->submitted()
                ->create();

            if ($status === ActivityStatus::ENCERRADA) {
                ActivityParticipation::factory()
                    ->for($activity)
                    ->for($otherResident)
                    ->validated()
                    ->create([
                        'status' => ParticipationStatus::VALIDADO,
                        'reviewed_by' => $moderator->id,
                    ]);
            }
        }
    }

    private function events(
        User $moderator,
        User $resident,
        User $otherResident,
        Zone $centro,
        Zone $sul,
        Category $category,
    ): void {
        $items = [
            [
                'title' => 'Mutirão de limpeza da praça central',
                'description' => 'Encontro para limpeza e separação de resíduos na praça.',
                'location' => 'Praça central',
                'zone' => $centro,
                'starts_at' => now()->addDays(5)->setTime(9, 0),
                'ends_at' => now()->addDays(5)->setTime(12, 0),
                'status' => EventStatus::PUBLICADO,
            ],
            [
                'title' => 'Palestra sobre reciclagem na escola',
                'description' => 'Palestra sobre separação de resíduos e reciclagem.',
                'location' => 'Escola Municipal do Bairro',
                'zone' => $sul,
                'starts_at' => now()->subDays(10)->setTime(14, 0),
                'ends_at' => now()->subDays(10)->setTime(16, 0),
                'status' => EventStatus::CONCLUIDO,
            ],
        ];

        foreach ($items as $item) {
            $title = $item['title'];

            $event = Event::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'category_id' => $category->id,
                    'zone_id' => $item['zone']->id,
                    'author_id' => $moderator->id,
                    'title' => $title,
                    'description' => $item['description'],
                    'location' => $item['location'],
                    'starts_at' => $item['starts_at'],
                    'ends_at' => $item['ends_at'],
                    'capacity' => 20,
                    'status' => $item['status'],
                ],
            );

            if ($event->registrations()->exists()) {
                continue;
            }

            EventRegistration::factory()->for($event)->for($resident)->create();

            if ($item['status'] === EventStatus::CONCLUIDO) {
                EventRegistration::factory()
                    ->for($event)
                    ->for($otherResident)
                    ->create(['status' => RegistrationStatus::PRESENTE]);
            }
        }
    }
}
