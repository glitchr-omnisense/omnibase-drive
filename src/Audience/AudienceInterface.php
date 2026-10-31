<?php

namespace Base\Drive\Audience;

use Base\Drive\Share\FileShare;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who a folder or a file of the drive is for (SharedPath::$audience holds its key): the bundle's
 * readers, users, members and staff, and whatever a site or another bundle adds - the associates
 * of a company (omnibase/estate's), a studio's customers. A service implementing it is one more
 * choice in « Pour qui », tagged drive.audience by autoconfiguration.
 */
#[AutoconfigureTag(self::TAG)]
interface AudienceInterface
{
    public const TAG = 'drive.audience';

    /** What sharedPath.audience holds: lower case, 16 characters at most ("readers", "associates"). */
    public function key(): string;

    /** Its name, a translation key of the messages domain ("share.audience.readers"). */
    public function label(): string;

    /** Font Awesome's icon for it ("fa-solid fa-earth-europe"). */
    public function icon(): string;

    /**
     * Whether it lets someone see a path that is for it: $user is null when nobody is signed in.
     * The staff see everything anyway, and the people a path is shared with see it whatever its
     * audience: neither is asked here.
     */
    public function lets(?UserInterface $user, string $path, FileShare $share): bool;
}
