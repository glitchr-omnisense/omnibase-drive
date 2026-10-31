<?php

namespace Base\Drive\Audience;

use Base\Drive\Share\FileShare;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Security\Core\User\UserInterface;

/** A path for the signed-in accounts. */
#[AsTaggedItem(priority: 20)]
final class UsersAudience implements AudienceInterface
{
    public const KEY = 'users';

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
        return 'fa-solid fa-user';
    }

    public function lets(?UserInterface $user, string $path, FileShare $share): bool
    {
        return null !== $user;
    }
}
