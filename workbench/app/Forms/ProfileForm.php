<?php

declare(strict_types=1);

namespace Workbench\App\Forms;

use Inertify\Form\Fields\Checkbox;
use Inertify\Form\Fields\Combobox;
use Inertify\Form\Fields\Fieldset;
use Inertify\Form\Fields\File;
use Inertify\Form\Fields\Repeater;
use Inertify\Form\Fields\Submit;
use Inertify\Form\Fields\Textarea;
use Inertify\Form\Fields\TextInput;
use Inertify\Form\Form;
use Inertify\Form\WizardConfig;

final class ProfileForm extends Form
{
    /** @return array<Fieldset> */
    public function fields(): array
    {
        return [
            Fieldset::make('Identity')
                ->id('identity')
                ->description('Start with the fields every profile needs.')
                ->fields([
                    TextInput::make('name', 'Name')
                        ->placeholder('Ada Lovelace')
                        ->required()
                        ->maxLength(120)
                        ->precognitive(),
                    TextInput::make('email', 'Email address')
                        ->placeholder('ada@example.com')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->precognitive(),
                    Checkbox::make('is_employed', 'I am currently employed')
                        ->default(false),
                    TextInput::make('company', 'Company')
                        ->placeholder('Analytical Engines Ltd.')
                        ->maxLength(120)
                        ->visibleWhen('is_employed', true)
                        ->clearWhenHidden(),
                ]),
            Fieldset::make('Experience')
                ->id('experience')
                ->description('Remote choices and nested collections use the same form state.')
                ->fields([
                    Combobox::make('skill', 'Primary skill')
                        ->source(route('workbench.skills.index', absolute: false))
                        ->searchable()
                        ->clearable()
                        ->searchParam('search')
                        ->valuesParam('values')
                        ->pageParam('page')
                        ->perPageParam('perPage')
                        ->preload()
                        ->perPage(4)
                        ->required(),
                    Repeater::make('projects', 'Projects')
                        ->schema([
                            TextInput::make('title', 'Project title')
                                ->required()
                                ->maxLength(120),
                            Textarea::make('summary', 'Summary')
                                ->maxLength(500),
                        ])
                        ->default([
                            ['title' => '', 'summary' => ''],
                        ])
                        ->rules(['array'])
                        ->minItems(1)
                        ->maxItems(5),
                ]),
            Fieldset::make('About')
                ->id('about')
                ->description('Uploads are controlled by the app and submitted as secure tokens.')
                ->fields([
                    File::make('avatar', 'Avatar')
                        ->image()
                        ->maxSize(5 * 1024),
                    Textarea::make('bio', 'Short biography')
                        ->placeholder('What are you working on?')
                        ->maxLength(1000),
                    Submit::make('Save profile'),
                ]),
        ];
    }

    public function wizard(): WizardConfig
    {
        return WizardConfig::make()
            ->step('Identity', 'Account and contact details')
            ->step('Experience', 'Skills and recent work')
            ->step('About', 'Biography and avatar')
            ->validateOnStep()
            ->labels(next: 'Continue', prev: 'Back', submit: 'Save profile');
    }
}
