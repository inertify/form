<?php

declare(strict_types=1);

use Inertify\Form\Fields\ColorPicker;
use Inertify\Form\Fields\DatePicker;
use Inertify\Form\Fields\OtpInput;
use Inertify\Form\Fields\Slider;
use Inertify\Form\Fields\Textarea;

it('rejects disabled calendar dates when submitted values include a time', function () {
    $single = DatePicker::make('appointment')
        ->withTime()
        ->disabledDates(['2026-08-11']);

    expect(validator(
        ['appointment' => '2026-08-11 09:30'],
        ['appointment' => $single->getRules()],
    )->fails())->toBeTrue()
        ->and(validator(
            ['appointment' => '2026-08-12 09:30'],
            ['appointment' => $single->getRules()],
        )->passes())->toBeTrue();

    $range = DatePicker::make('availability')
        ->range()
        ->withTime()
        ->valueFormat('DD/MM/YYYY HH:mm')
        ->disabledDates(['2026-08-13']);

    expect(validator(
        ['availability' => ['12/08/2026 08:00', '13/08/2026 17:00']],
        [
            'availability' => $range->getRules(),
            'availability.*' => $range->getItemRules(),
        ],
    )->fails())->toBeTrue()
        ->and(validator(
            ['availability' => ['12/08/2026 08:00', '14/08/2026 17:00']],
            [
                'availability' => $range->getRules(),
                'availability.*' => $range->getItemRules(),
            ],
        )->passes())->toBeTrue();
});

it('excludes presenter-owned field modifiers from the headless PHP API', function () {
    expect(Textarea::class)->not->toHaveMethods([
        'rows',
        'cols',
        'autoResize',
        'minHeight',
        'maxHeight',
        'showCharacterCount',
    ])->and(DatePicker::class)->not->toHaveMethods([
        'numberOfMonths',
        'markers',
        'highlightedDates',
    ])->and(OtpInput::class)->not->toHaveMethods([
        'separator',
        'groupSize',
    ])->and(Slider::class)->not->toHaveMethod('unitPosition')
        ->and(ColorPicker::class)->not->toHaveMethods([
            'showAlpha',
            'showEyeDropper',
        ]);

    expect(Textarea::make('summary')->maxLength(200)->toArray())
        ->not->toHaveKeys(['rows', 'cols', 'autoResize', 'minHeight', 'maxHeight', 'showCharacterCount'])
        ->and(DatePicker::make('date')->toArray())
        ->not->toHaveKeys(['numberOfMonths', 'markers', 'highlightedDates'])
        ->and(OtpInput::make('code')->length(6)->toArray())
        ->not->toHaveKeys(['separator', 'groupSize'])
        ->and(Slider::make('amount')->unit('$')->toArray())
        ->unit->toBe('$')
        ->not->toHaveKey('unitPosition')
        ->and(ColorPicker::make('color')->alpha()->eyedropper()->toArray())
        ->alpha->toBeTrue()
        ->eyedropper->toBeTrue()
        ->not->toHaveKeys(['showAlpha', 'showEyeDropper']);
});
