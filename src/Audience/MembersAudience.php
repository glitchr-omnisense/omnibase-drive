<?php

namespace Base\Drive\Audience;

use Base\Entity\User;
use Base\Drive\Share\FileShare;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Security\Core\User\UserInterface;

/** A path for the people of the workspace the path is in (the folder made one, and its people). */
#[AsTaggedItem(priority: 10)]
final class MembersAudience implements AudienceInterface
{
    public const KEY = 'members';

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
        return 'fa-solid fa-user-group';
    }

    public function lets(?UserInterface $user, string $path, FileShare $share): bool
    {
        return $user instanceof User && (bool) $share->workspace($path)?->hasMember($user);
    }
}
