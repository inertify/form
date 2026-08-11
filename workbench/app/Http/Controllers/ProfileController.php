<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Inertify\Form\Validate;
use Workbench\App\Forms\ProfileForm;
use Workbench\App\Models\User;

final class ProfileController
{
    public function create(): Response
    {
        $form = ProfileForm::make()
            ->route('workbench.profiles.store')
            ->post()
            ->unsavedWarning()
            ->scrollToFirstError();

        return Inertia::render('Forms/Demo', [
            'form' => $form,
            'mode' => 'create',
        ]);
    }

    public function edit(): Response
    {
        $profile = new User;
        $profile->forceFill([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'is_employed' => true,
            'company' => 'Analytical Engines Ltd.',
            'skill' => 'php',
            'projects' => [
                [
                    'title' => 'Headless forms',
                    'summary' => 'A schema-driven form with application-owned markup.',
                ],
            ],
            'bio' => 'Mathematician, writer, and enthusiastic Laravel package tester.',
        ]);

        $form = ProfileForm::make()
            ->bind($profile)
            ->route('workbench.profiles.update', ['profile' => 1])
            ->patch()
            ->unsavedWarning()
            ->scrollToFirstError();

        return Inertia::render('Forms/Demo', [
            'form' => $form,
            'mode' => 'edit',
        ]);
    }

    public function store(#[Validate] ProfileForm $form): RedirectResponse
    {
        return $this->save($form);
    }

    public function update(#[Validate] ProfileForm $form, int $profile): RedirectResponse
    {
        return $this->save($form, $profile);
    }

    public function skills(Request $request): JsonResponse
    {
        $skills = collect([
            ['value' => 'php', 'label' => 'PHP'],
            ['value' => 'laravel', 'label' => 'Laravel'],
            ['value' => 'typescript', 'label' => 'TypeScript'],
            ['value' => 'vue', 'label' => 'Vue'],
            ['value' => 'inertia', 'label' => 'Inertia'],
            ['value' => 'testing', 'label' => 'Automated testing'],
            ['value' => 'accessibility', 'label' => 'Accessibility'],
            ['value' => 'design-systems', 'label' => 'Design systems'],
        ]);

        $selected = array_filter((array) $request->input('values', []), 'is_string');
        $search = mb_strtolower((string) $request->input('search', $request->input('q', '')));

        if ($selected !== []) {
            $skills = $skills->whereIn('value', $selected)->values();
        } elseif ($search !== '') {
            $skills = $skills->filter(
                fn (array $skill): bool => str_contains(mb_strtolower($skill['label']), $search),
            )->values();
        }

        $page = max(1, $request->integer('page', 1));
        $perPage = min(20, max(1, $request->integer('perPage', $request->integer('per_page', 4))));
        $lastPage = max(1, (int) ceil($skills->count() / $perPage));

        return response()->json([
            'data' => $skills->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'next_page_url' => $page < $lastPage
                ? route('workbench.skills.index', ['page' => $page + 1], absolute: false)
                : null,
        ]);
    }

    private function save(ProfileForm $form, ?int $profile = null): RedirectResponse
    {
        $data = $form->validated(files: false);
        $avatar = $form->upload('avatar');

        return back()->with(
            'success',
            sprintf(
                '%s profile for %s%s.',
                $profile === null ? 'Created' : 'Updated',
                (string) $data['name'],
                $avatar === null ? '' : ' with '.$avatar->getName(),
            ),
        );
    }
}
