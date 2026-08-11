<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Concerns;

use Closure;
use Inertify\Form\Support\Value;

trait Authorizable
{
    protected bool|Closure $authorization = true;

    public function authorize(bool|Closure $authorization = true): static
    {
        $this->authorization = $authorization;

        return $this;
    }

    public function authorizedWhen(bool|Closure $condition): static
    {
        return $this->authorize($condition);
    }

    public function authorizedUnless(bool|Closure $condition): static
    {
        $this->authorization = $condition instanceof Closure
            ? fn (): bool => ! (bool) Value::resolve($condition, $this->evaluationParameters())
            : ! $condition;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return (bool) Value::resolve($this->authorization, $this->evaluationParameters());
    }

    /**
     * @return array<string, mixed>
     */
    protected function evaluationParameters(): array
    {
        return [];
    }
}
