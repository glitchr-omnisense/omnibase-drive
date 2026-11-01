<?php

namespace Base\Drive\Security\Voter;

use Base\Drive\Audience\Audiences;
use Base\Drive\Share\FileShare;
use Base\Drive\Share\Retention;
use Base\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who sees a path of the drive (SHARE_VIEW, the path as subject), who manages it (SHARE_MANAGE:
 * the staff, drive.staff_role), and who works in a workspace - its tasks, the files its people
 * bring (SHARE_WORK, the path as subject: its members and the staff). A path is for its audience
 * (FileShare::audience, answered by Base\Drive\Audience\Audiences: the readers, the signed-in
 * users, the members of its workspace, the staff only, and the site's own); and, whatever its
 * audience, for the people it was shared with (FileShare::isSharedWith: a workspace's people, in
 * it or above it).
 *
 * The retention lock (drive.retention, Share\Retention): SHARE_ALTER - delete, overwrite, rename or
 * move a path - is the staff's, and nobody's in a locked folder, the team included; SHARE_RETAIN -
 * lift a folder's lock or put it back - is the superadmin's alone (drive.retention_role), with
 * retention on.
 */
final class ShareVoter extends Voter
{
    public const VIEW = 'SHARE_VIEW';
    public const MANAGE = 'SHARE_MANAGE';
    public const WORK = 'SHARE_WORK';
    public const ALTER = 'SHARE_ALTER';
    public const RETAIN = 'SHARE_RETAIN';

    public function __construct(
        private readonly FileShare $share,
        private readonly Audiences $audiences,
        private readonly Security $security,
        #[Autowire('%drive.staff_role%')] private readonly string $staffRole = 'ROLE_STAFF',
        private readonly ?Retention $retention = null,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute || (\in_array($attribute, [self::VIEW, self::WORK, self::ALTER, self::RETAIN], true) && \is_string($subject));
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (self::RETAIN === $attribute) {
            return null !== $this->retention && $this->retention->enabled() && $this->security->isGranted($this->retention->role());
        }
        $staff = $this->security->isGranted($this->staffRole);
        if (self::ALTER === $attribute) {
            return $staff && !$this->retention?->isLockedTree($subject);
        }
        if (self::MANAGE === $attribute || $staff) {
            return $staff;
        }

        $user = $token->getUser();
        if (self::WORK === $attribute) {
            return $user instanceof User && (bool) $this->share->workspace($subject)?->hasMember($user);
        }

        // Shared with them by name: theirs to see, whoever else it is for.
        if ($user instanceof User && $this->share->isSharedWith($subject, $user)) {
            return true;
        }

        return $this->audiences->get($this->share->audience($subject))->lets($user instanceof UserInterface ? $user : null, $subject, $this->share);
    }
}
