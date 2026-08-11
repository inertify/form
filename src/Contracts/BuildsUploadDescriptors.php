<?php

declare(strict_types=1);

namespace Inertify\Form\Contracts;

interface BuildsUploadDescriptors
{
    /**
     * @return array{
     *     strategy: 'temporary'|'form'|'chunked'|'direct',
     *     endpoints: array<string, mixed>,
     *     limits: array<string, int>,
     *     disk: string|null,
     *     rulesToken: string|null,
     *     requiresRulesToken: bool
     * }
     */
    public function descriptor(
        string $strategy = 'temporary',
        ?string $disk = null,
        ?string $rulesToken = null,
        bool $requiresRulesToken = false,
        ?string $routeName = null,
    ): array;

    /**
     * Return the documented flat File-field transport props, including the
     * additive headless upload descriptor.
     *
     * @return array<string, mixed>
     */
    public function flatProps(
        string $strategy = 'temporary',
        ?string $disk = null,
        ?string $rulesToken = null,
        bool $requiresRulesToken = false,
        ?string $routeName = null,
    ): array;
}
