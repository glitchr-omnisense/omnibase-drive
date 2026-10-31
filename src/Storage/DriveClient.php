<?php

namespace Base\Drive\Storage;

use Google\Client;
use Google\Service\Drive;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Google, for the documents: the consent screen (offline access, the whole
 * Drive - so files dropped into the folder from Drive itself show up), and
 * a Drive service on the stored refresh token. The OAuth client is the
 * site's (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET); its consent screen must
 * be published, or Google drops the token after seven days.
 */
class DriveClient
{
    public function __construct(
        private readonly DriveSettings $settings,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%env(default::GOOGLE_CLIENT_ID)%')] private readonly ?string $clientId = null,
        #[Autowire('%env(default::GOOGLE_CLIENT_SECRET)%')] private readonly ?string $clientSecret = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return (bool) $this->clientId && (bool) $this->clientSecret;
    }

    public function client(): Client
    {
        $client = new Client();
        $client->setClientId((string) $this->clientId);
        $client->setClientSecret((string) $this->clientSecret);
        $client->setRedirectUri($this->urls->generate('app_admin_drive_callback', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $client->setScopes([Drive::DRIVE, 'email']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    public function authorizationUrl(string $state): string
    {
        $client = $this->client();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /** @return array{refresh_token: string, email: ?string} */
    public function exchange(string $code): array
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error']) || empty($token['refresh_token'])) {
            throw new \RuntimeException($token['error_description'] ?? $token['error'] ?? 'Google gave no refresh token.');
        }
        $email = null;
        if (!empty($token['id_token']) && ($payload = $client->verifyIdToken($token['id_token']))) {
            $email = $payload['email'] ?? null;
        }

        return ['refresh_token' => $token['refresh_token'], 'email' => $email];
    }

    public function drive(): Drive
    {
        $client = $this->client();
        $client->fetchAccessTokenWithRefreshToken((string) $this->settings->refreshToken());

        return new Drive($client);
    }
}
