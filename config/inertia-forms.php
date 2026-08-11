<?php

declare(strict_types=1);

return [
    'file_uploads' => [
        'route_prefix' => '/_inertia-forms',
        'route_name' => 'inertia-forms.',
        'middleware' => ['web', 'auth'],

        'temporary_uploads' => [
            'disk' => '',
            'lifetime' => 3600,
            'max_size' => 10240,

            'chunked' => [
                'size' => 5 * 1024 * 1024,
                'max_size' => 2 * 1024 * 1024,
            ],
        ],

        // Laravel S3-compatible disks use their native multipart API when the
        // installed adapter exposes a client. Other disks use local PUT routes.
        'direct_to_storage' => [
            'disk' => '',
            'url_lifetime' => 900,
            // Bytes. S3 requires at least 5 MiB for every non-final part.
            'part_size' => 16 * 1024 * 1024,
            // Bytes. Files larger than this value use multipart uploads.
            'multipart_threshold' => 100 * 1024 * 1024,
            // KiB.
            'max_size' => 5 * 1024 * 1024,
        ],
    ],

    'authorization' => [
        'throw_on_unauthorized' => false,
    ],
];
