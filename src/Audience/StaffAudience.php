<?php

namespace Base\Drive\Audience;

use Base\Drive\Share\FileShare;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Security\Core\User\UserInterface;

/** A path for the staff only (who see everything anyway): nobody else. */
#[AsTaggedItem(priority: -100)]
final class StaffAudience implements AudienceInterface
{
    public const KEY = 'staff';

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
        return 'fa-solid fa-lock';
    }

    public function lets(?UserInterface $user, string $path, FileShare $share): bool
    {
        return false;
    }
}
