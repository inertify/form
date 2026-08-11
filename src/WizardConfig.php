<?php

declare(strict_types=1);

namespace Inertify\Form;

use Illuminate\Contracts\Support\Arrayable;
use Inertify\Form\Fields\Fieldset;
use JsonSerializable;

/** @implements Arrayable<string, mixed> */
class WizardConfig implements Arrayable, JsonSerializable
{
    /** @var list<Fieldset|array<string, mixed>|int> */
    protected array $wizardSteps = [];

    protected bool $wizardEnabled = true;

    protected bool $canSkip = false;

    protected bool $validatesOnStep = false;

    protected ?string $nextButtonLabel = null;

    protected ?string $previousButtonLabel = null;

    protected ?string $submitButtonLabel = null;

    /** @param array<Fieldset|array<string, mixed>|int> $steps */
    final protected function __construct(array $steps = [])
    {
        $this->steps($steps);
    }

    /** @param array<Fieldset|array<string, mixed>|int> $steps */
    public static function make(array $steps = []): static
    {
        return new static($steps);
    }

    /** @param array<Fieldset|array<string, mixed>|int> $steps */
    public function steps(array $steps): static
    {
        $this->wizardSteps = array_values($steps);

        return $this;
    }

    public function step(?string $title = null, ?string $description = null): static
    {
        $this->wizardSteps[] = compact('title', 'description');

        return $this;
    }

    public function enabled(bool $enabled = true): static
    {
        $this->wizardEnabled = $enabled;

        return $this;
    }

    public function allowSkip(bool $allow = true): static
    {
        $this->canSkip = $allow;

        return $this;
    }

    public function validateOnStep(bool $validate = true): static
    {
        $this->validatesOnStep = $validate;

        return $this;
    }

    public function labels(?string $next = null, ?string $prev = null, ?string $submit = null): static
    {
        if ($next !== null) {
            $this->nextButtonLabel = $next;
        }

        if ($prev !== null) {
            $this->previousButtonLabel = $prev;
        }

        if ($submit !== null) {
            $this->submitButtonLabel = $submit;
        }

        return $this;
    }

    public function nextLabel(string $label): static
    {
        $this->nextButtonLabel = $label;

        return $this;
    }

    public function prevLabel(string $label): static
    {
        $this->previousButtonLabel = $label;

        return $this;
    }

    public function submitLabel(string $label): static
    {
        $this->submitButtonLabel = $label;

        return $this;
    }

    /**
     * @param  list<Fieldset>  $fieldsets
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toArrayFor(array $fieldsets, array $data = []): array
    {
        $steps = [];
        $configured = $this->wizardSteps;
        $usesExplicitFieldsetSelection = collect($configured)->contains(
            fn (mixed $step): bool => $step instanceof Fieldset || is_int($step),
        );

        foreach ($fieldsets as $index => $fieldset) {
            if (! $fieldset->isAuthorized() || ! $fieldset->isVisible($data) || ! $this->hasVisibleFields($fieldset, $data)) {
                continue;
            }

            if ($configured === []) {
                $steps[] = ['fieldset' => $index];

                continue;
            }

            if ($usesExplicitFieldsetSelection) {
                $configuration = collect($configured)->first(
                    fn (mixed $step): bool => $step === $fieldset || $step === $index,
                );

                if ($configuration === null) {
                    continue;
                }
            } else {
                $configuration = $configured[$index] ?? null;
            }

            $steps[] = [
                'fieldset' => $index,
                ...(is_array($configuration) ? $configuration : []),
            ];
        }

        return [
            'enabled' => $this->wizardEnabled,
            'allowSkip' => $this->canSkip,
            'validateOnStep' => $this->validatesOnStep,
            'steps' => $steps,
            'nextLabel' => $this->nextButtonLabel,
            'prevLabel' => $this->previousButtonLabel,
            'submitLabel' => $this->submitButtonLabel,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->toArrayFor([]);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param array<string, mixed> $data */
    protected function hasVisibleFields(Fieldset $fieldset, array $data): bool
    {
        foreach ($fieldset->getFields() as $field) {
            if (! $field->isAuthorized() || ! $field->isVisible($data)) {
                continue;
            }

            if (! $field instanceof Fieldset || $this->hasVisibleFields($field, $data)) {
                return true;
            }
        }

        return false;
    }
}
