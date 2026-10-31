<?php

namespace Base\Drive\Storage;

use Base\Service\SettingBagInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * What the site keeps of its Google Drive: the refresh token (encrypted with
 * the app secret: it opens the whole Drive), the folder the documents live
 * in, and who connected it. In omnibase's settings, under the names the sites
 * stored them with (app.drive.*), sealed with the same key: a Drive connected
 * before the bundle stays connected.
 */
class DriveSettings
{
    private const TOKEN = 'app.drive.refresh_token';
    private const FOLDER = 'app.drive.folder_id';
    private const FOLDER_NAME = 'app.drive.folder_name';
    private const ACCOUNT = 'app.drive.account';
    private const SHARED_DRIVE = 'app.drive.shared_drive_id';

    public function __construct(
        private readonly SettingBagInterface $settings,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
    }

    public function isConnected(): bool
    {
        return null !== $this->refreshToken() && null !== $this->folderId();
    }

    public function refreshToken(): ?string
    {
        $sealed = $this->scalar(self::TOKEN);
        if (!$sealed || !($raw = base64_decode($sealed, true)) || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());

        return false === $plain ? null : $plain;
    }

    public function connect(string $refreshToken, ?string $account): void
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $this->settings->set(self::TOKEN, base64_encode($nonce.sodium_crypto_secretbox($refreshToken, $nonce, $this->key())));
        $this->settings->set(self::ACCOUNT, $account);
    }

    public function disconnect(): void
    {
        foreach ([self::TOKEN, self::ACCOUNT, self::FOLDER, self::FOLDER_NAME, self::SHARED_DRIVE] as $path) {
            $this->settings->set($path, null);
        }
    }

    public function folderId(): ?string { return $this->scalar(self::FOLDER); }
    public function folderName(): ?string { return $this->scalar(self::FOLDER_NAME); }
    public function account(): ?string { return $this->scalar(self::ACCOUNT); }
    public function sharedDriveId(): ?string { return $this->scalar(self::SHARED_DRIVE); }

    public function useFolder(string $id, string $name, ?string $sharedDriveId = null): void
    {
        $this->settings->set(self::FOLDER, $id);
        $this->settings->set(self::FOLDER_NAME, $name);
        $this->settings->set(self::SHARED_DRIVE, $sharedDriveId);
    }

    private function scalar(string $path): ?string
    {
        try {
            $value = $this->settings->getScalar($path);
        } catch (\Throwable) {
            return null;
        }

        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function key(): string
    {
        return sodium_crypto_generichash('app.drive|'.$this->secret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
