<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Inertify\Form\Contracts\BuildsUploadDescriptors;
use InvalidArgumentException;

final class UploadRouteDescriptor implements BuildsUploadDescriptors
{
    public function descriptor(
        string $strategy = 'temporary',
        ?string $disk = null,
        ?string $rulesToken = null,
        bool $requiresRulesToken = false,
        ?string $routeName = null,
    ): array {
        if (! in_array($strategy, ['temporary', 'form', 'chunked', 'direct'], true)) {
            throw new InvalidArgumentException('Unknown upload strategy ['.$strategy.'].');
        }

        $endpoints = $this->endpoints($strategy, $routeName);

        return [
            'strategy' => $strategy,
            'endpoints' => $endpoints,
            'limits' => [
                'maxSizeKiB' => $this->configInt('file_uploads.temporary_uploads.max_size', 10240),
                'chunkSizeBytes' => $this->configInt('file_uploads.temporary_uploads.chunked.size', 5 * 1024 * 1024),
                'directMaxSizeKiB' => $this->configInt('file_uploads.direct_to_storage.max_size', 5 * 1024 * 1024),
                'partSizeBytes' => $this->configInt('file_uploads.direct_to_storage.part_size', 16 * 1024 * 1024),
                'multipartThresholdBytes' => $this->configInt('file_uploads.direct_to_storage.multipart_threshold', 100 * 1024 * 1024),
            ],
            'disk' => $disk,
            'rulesToken' => $rulesToken,
            'requiresRulesToken' => $requiresRulesToken,
        ];
    }

    /**
     * Canonical compatibility props used by the documented File field API.
     *
     * @return array<string, mixed>
     */
    public function flatProps(
        string $strategy = 'temporary',
        ?string $disk = null,
        ?string $rulesToken = null,
        bool $requiresRulesToken = false,
        ?string $routeName = null,
    ): array {
        $resource = $this->descriptor($strategy, $disk, $rulesToken, $requiresRulesToken, $routeName);
        /** @var array<string, mixed> $endpoints */
        $endpoints = $resource['endpoints'];
        $chunked = is_array($endpoints['chunked'] ?? null) ? $endpoints['chunked'] : null;
        $direct = is_array($endpoints['direct'] ?? null) ? $endpoints['direct'] : null;

        return [
            'storeWithForm' => $strategy === 'form',
            'temporaryUploadUrl' => data_get($endpoints, 'store.url'),
            'temporaryUploadDeleteUrl' => data_get($endpoints, 'destroy.url'),
            'chunked' => $strategy === 'chunked',
            'chunkSize' => $resource['limits']['chunkSizeBytes'],
            'chunkedUrls' => $chunked === null ? null : $this->endpointUrls($chunked),
            'directToStorage' => $strategy === 'direct',
            'uploadDisk' => $disk,
            'uploadPartSize' => $resource['limits']['partSizeBytes'],
            'uploadMultipartThreshold' => $resource['limits']['multipartThresholdBytes'],
            'directUploadUrls' => $direct === null ? null : $this->endpointUrls($direct),
            'uploadRulesToken' => $rulesToken,
            'requiresUploadRulesToken' => $requiresRulesToken,
            'upload' => $resource,
        ];
    }

    /** @return array<string, mixed> */
    private function endpoints(string $strategy, ?string $routeName): array
    {
        if ($strategy === 'form') {
            return [];
        }

        $prefix = $this->routePrefix($routeName);
        $endpoints = [
            'destroy' => $this->endpoint('DELETE', $prefix.'file-upload.destroy'),
        ];

        if ($strategy === 'temporary') {
            $endpoints['store'] = $this->endpoint('POST', $prefix.'file-upload.store');
        }

        if ($strategy === 'chunked') {
            $endpoints['chunked'] = [
                'start' => $this->endpoint('POST', $prefix.'file-upload.chunked.start'),
                'status' => $this->endpoint('GET', $prefix.'file-upload.chunked.status'),
                'append' => $this->endpoint('POST', $prefix.'file-upload.chunked.append'),
                'complete' => $this->endpoint('POST', $prefix.'file-upload.chunked.complete'),
                'abort' => $this->endpoint('DELETE', $prefix.'file-upload.chunked.abort'),
            ];
        }

        if ($strategy === 'direct') {
            $endpoints['direct'] = [
                'start' => $this->endpoint('POST', $prefix.'file-upload.direct.start'),
                'object' => $this->endpoint('PUT', $prefix.'file-upload.direct.object'),
                'signPart' => $this->endpoint('POST', $prefix.'file-upload.direct.part'),
                'status' => $this->endpoint('GET', $prefix.'file-upload.direct.status'),
                'complete' => $this->endpoint('POST', $prefix.'file-upload.direct.complete'),
                'abort' => $this->endpoint('DELETE', $prefix.'file-upload.direct.abort'),
            ];
        }

        return $endpoints;
    }

    /**
     * @param  array<string, mixed>  $endpoints
     * @return array<string, string|null>
     */
    private function endpointUrls(array $endpoints): array
    {
        return array_map(
            static fn (mixed $endpoint): ?string => is_array($endpoint) && is_string($endpoint['url'] ?? null)
                ? $endpoint['url']
                : null,
            $endpoints,
        );
    }

    /** @return array{method: string, url: string} */
    private function endpoint(string $method, string $routeName): array
    {
        return [
            'method' => $method,
            'url' => route($routeName, absolute: false),
        ];
    }

    private function routePrefix(?string $routeName): string
    {
        $routeName ??= config('inertia-forms.file_uploads.route_name', 'inertia-forms.');
        $routeName = is_string($routeName) ? trim($routeName, '.') : 'inertia-forms';

        return $routeName.'.';
    }

    private function configInt(string $key, int $default): int
    {
        return (int) config('inertia-forms.'.$key, $default);
    }
}
