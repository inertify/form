<?php

declare(strict_types=1);

namespace Inertify\Form\Console\Commands;

use Illuminate\Console\Command;
use Inertify\Form\Uploads\UploadManager;

final class CleanupUploadsCommand extends Command
{
    protected $signature = 'form:cleanup-uploads
        {--lifetime= : Delete uploads older than this many seconds instead of the configured lifetime}';

    protected $description = 'Remove expired temporary and direct form uploads';

    public function handle(UploadManager $uploads): int
    {
        $lifetime = $this->option('lifetime');

        if ($lifetime !== null && (! is_numeric($lifetime) || (int) $lifetime < 0)) {
            $this->components->error('The upload lifetime must be a non-negative number of seconds.');

            return self::FAILURE;
        }

        $report = $uploads->cleanupReport(
            $lifetime === null ? null : (int) $lifetime,
        );

        $this->components->info(sprintf(
            'Removed %d expired upload%s.',
            $report['removed'],
            $report['removed'] === 1 ? '' : 's',
        ));

        foreach ($report['errors'] as $error) {
            $this->components->warn($error);
        }

        if ($report['failed'] > 0) {
            $this->components->error(sprintf(
                '%d upload cleanup operation%s failed and can be retried.',
                $report['failed'],
                $report['failed'] === 1 ? '' : 's',
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
