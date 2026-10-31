<?php

namespace Base\Drive\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use Masbug\Flysystem\GoogleDriveAdapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Where the drive's files are (the service drive.storage, alias documents.storage, read by
 * Base\Drive\Share\FileShare): the site's Google Drive folder once connected from the back
 * office (google/apiclient and masbug/flysystem-google-drive-ext installed), the site's own
 * storage otherwise (drive.local_storage, local.share by default) - and always in the tests.
 */
class DocumentsStorage
{
    public function __construct(
        private readonly DriveSettings $settings,
        private readonly DriveClient $client,
        private readonly FilesystemOperator $local,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function create(): FilesystemOperator
    {
        if (!$this->isDrive()) {
            return $this->local;
        }

        try {
            $options = $this->settings->sharedDriveId() ? ['teamDriveId' => $this->settings->sharedDriveId()] : [];

            return new Filesystem(new GoogleDriveAdapter($this->client->drive(), (string) $this->settings->folderId(), $options));
        } catch (\Throwable $e) {
            // Google unreachable or the token revoked: the documents fall back, the back office says why.
            $this->logger?->error('Google Drive unavailable: {message}', ['message' => $e->getMessage()]);

            return $this->local;
        }
    }

    public function isDrive(): bool
    {
        return 'test' !== $this->environment && class_exists(GoogleDriveAdapter::class) && $this->client->isConfigured() && $this->settings->isConnected();
    }
}
