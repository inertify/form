<?php

declare(strict_types=1);

namespace Workbench\App\Forms;

use Inertify\Form\Fields\Blocks;
use Inertify\Form\Fields\BlockSet;
use Inertify\Form\Fields\Checkbox;
use Inertify\Form\Fields\CheckboxGroup;
use Inertify\Form\Fields\ColorPicker;
use Inertify\Form\Fields\Combobox;
use Inertify\Form\Fields\Composer;
use Inertify\Form\Fields\DatePicker;
use Inertify\Form\Fields\Fieldset;
use Inertify\Form\Fields\File;
use Inertify\Form\Fields\Hidden;
use Inertify\Form\Fields\KeyValue;
use Inertify\Form\Fields\Link;
use Inertify\Form\Fields\OtpInput;
use Inertify\Form\Fields\Radio;
use Inertify\Form\Fields\Repeater;
use Inertify\Form\Fields\RichText;
use Inertify\Form\Fields\Slider;
use Inertify\Form\Fields\Slug;
use Inertify\Form\Fields\Submit;
use Inertify\Form\Fields\Textarea;
use Inertify\Form\Fields\TextInput;
use Inertify\Form\Fields\TimePicker;
use Inertify\Form\Fields\Toggle;
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
                ->description('Shadcn Vue Input, Input Group, Number Field, Input OTP, and hidden values.')
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
                    Slug::make('username', 'Profile slug')
                        ->from('name')
                        ->placeholder('ada-lovelace')
                        ->lowercase()
                        ->nullable(),
                    TextInput::make('age', 'Age')
                        ->integer()
                        ->min(16)
                        ->max(120)
                        ->default(28),
                    OtpInput::make('verification_code', 'Verification code')
                        ->length(6)
                        ->numeric()
                        ->help('A six-digit Input OTP example.')
                        ->default('183742'),
                    Hidden::make('source')->default('shadcn-vue-workbench'),
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
                ->description('Shadcn Vue Combobox, Select, Radio Group, Switch, Slider, and repeating fields.')
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
                        ->ruleIn(false)
                        ->required(),
                    Radio::make('work_mode', 'Preferred work mode')
                        ->options([
                            'remote' => 'Remote',
                            'hybrid' => 'Hybrid',
                            'office' => 'Office',
                        ])
                        ->default('remote'),
                    CheckboxGroup::make('interests', 'Interests')
                        ->options([
                            'accessibility' => 'Accessibility',
                            'design-systems' => 'Design systems',
                            'testing' => 'Automated testing',
                        ])
                        ->minSelected(1)
                        ->maxSelected(3)
                        ->default(['accessibility']),
                    Toggle::make('notifications', 'Project notifications')
                        ->onLabel('Enabled')
                        ->offLabel('Disabled')
                        ->default(true),
                    Slider::make('experience_years', 'Years of experience')
                        ->min(0)
                        ->max(20)
                        ->step(1)
                        ->unit(' years')
                        ->default(5),
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
            Fieldset::make('Details')
                ->id('details')
                ->description('Shadcn Vue Date Picker, Calendar, native time input, color input, Select, and Tags-style key/value rows.')
                ->fields([
                    DatePicker::make('available_from', 'Available from')
                        ->minDate('2024-01-01')
                        ->maxDate('2030-12-31')
                        ->clearable()
                        ->nullable(),
                    TimePicker::make('contact_time', 'Best contact time')
                        ->use24HourTime()
                        ->minuteStep(15)
                        ->clearable()
                        ->nullable(),
                    ColorPicker::make('accent_color', 'Accent color')
                        ->swatches(['#0f172a', '#2563eb', '#7c3aed', '#059669', '#e11d48'])
                        ->default('#2563eb')
                        ->clearable(),
                    Link::make('website', 'Website')
                        ->structured()
                        ->withLabel()
                        ->withTarget()
                        ->allowedSchemes('https', 'mailto')
                        ->nullable(),
                    KeyValue::make('metadata', 'Profile metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addLabel('Add metadata')
                        ->minItems(1)
                        ->maxItems(5)
                        ->default(['timezone' => 'Europe/Kyiv']),
                ]),
            Fieldset::make('Content')
                ->id('content')
                ->description('Shadcn Vue Textarea, file Input with Attachment states, and collection controls.')
                ->fields([
                    Textarea::make('bio', 'Short biography')
                        ->placeholder('What are you working on?')
                        ->maxLength(1000)
                        ->nullable(),
                    Composer::make('message', 'Message composer')
                        ->maxLength(500)
                        ->nullable(),
                    RichText::make('introduction', 'Rich text introduction')
                        ->maxLength(2000)
                        ->nullable(),
                    File::make('avatar', 'Avatar')
                        ->image()
                        ->maxSize(5 * 1024)
                        ->nullable(),
                    Blocks::make('content_blocks', 'Content blocks')
                        ->sets([
                            BlockSet::make('callout', 'Callout')
                                ->description('A titled callout block.')
                                ->schema([
                                    TextInput::make('title', 'Title')->required(),
                                    Textarea::make('body', 'Body'),
                                ]),
                            BlockSet::make('quote', 'Quote')
                                ->description('A quotation and attribution.')
                                ->schema([
                                    Textarea::make('quote', 'Quote')->required(),
                                    TextInput::make('credit', 'Credit'),
                                ]),
                        ])
                        ->maxItems(4)
                        ->reorderable()
                        ->default([
                            [
                                'type' => 'callout',
                                'data' => ['title' => 'Hello from Inertify', 'body' => 'Every control is application-owned.'],
                            ],
                        ]),
                    Submit::make('Save profile'),
                ]),
        ];
    }

    public function wizard(): WizardConfig
    {
        return WizardConfig::make()
            ->step('Identity', 'Account and contact details')
            ->step('Experience', 'Choices, switches, and collections')
            ->step('Details', 'Dates, colors, links, and metadata')
            ->step('Content', 'Long-form content, uploads, and blocks')
            ->validateOnStep()
            ->labels(next: 'Continue', prev: 'Back', submit: 'Save profile');
    }
}
