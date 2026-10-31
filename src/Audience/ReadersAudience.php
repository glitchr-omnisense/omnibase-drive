<?php

namespace Base\Drive\Audience;

use Base\Drive\Share\FileShare;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Security\Core\User\UserInterface;

/** A path for everyone, signed in or not. */
#[AsTaggedItem(priority: 30)]
final class ReadersAudience implements AudienceInterface
{
    public const KEY = 'readers';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'share.audience.'.self::KEY;
    }

    public function icon(): string
    {
        return 'fa-solid fa-earth-europe';
    }

    public function lets(?UserInterface $user, string $path, FileShare $share): bool
    {
        return true;
    }
}
