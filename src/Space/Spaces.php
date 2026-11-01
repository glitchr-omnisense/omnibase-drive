<?php

namespace Base\Drive\Space;

use Base\Drive\Audience\StaffAudience;
use Base\Drive\Entity\ShareGroup;
use Base\Drive\Share\FileShare;
use Base\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Each person's space in the drive, and files shared on upload with named people: a file added
 * goes, besides the folder it was added to, into the space of each person named - and of each
 * person of a group named (Entity\ShareGroup) -, the space made if missing, reused otherwise,
 * shared with them read only unless the uploader lets them bring files too.
 *
 * Where a space is: the first provider to answer (SpaceProviderInterface, tagged drive.space -
 * omnibase/estate's tenants, owners and partners), otherwise drive.spaces_root/<their name>, the
 * team's (the staff audience) and theirs alone.
 */
class Spaces
{
    /** @param iterable<SpaceProviderInterface> $providers */
    public function __construct(
        private readonly FileShare $share,
        #[AutowireIterator(SpaceProviderInterface::TAG)] private readonly iterable $providers = [],
        #[Autowire('%drive.spaces_root%')] private readonly string $root = 'Spaces',
    ) {
    }

    /** A person's space: made if missing, reused otherwise, shared with them (read only, unless $deposit); its path. */
    public function of(User $user, bool $deposit = false): string
    {
        $path = null;
        foreach ($this->providers as $provider) {
            if (null !== $path = $provider->spaceOf($user)) {
                break;
            }
        }
        $path ??= $this->made($user);
        // Shared with them, whatever the provider did; a right to bring files is given, never taken back here.
        if (!$this->share->isSharedWith($path, $user) || $deposit) {
            $this->share->shareWith($path, $user, $deposit ? true : null);
        }

        return $path;
    }

    /**
     * A file of the drive shared with people: a copy in each one's space (a group: each of its
     * people's), beside what is there, never over it - none where the file already is in their
     * space. Each person once, however often named.
     *
     * @param iterable<User|ShareGroup> $recipients
     *
     * @return array<string, string> each copy's path => the space it went to
     */
    public function deliver(string $file, iterable $recipients, bool $deposit = false): array
    {
        $people = [];
        foreach ($recipients as $recipient) {
            foreach ($recipient instanceof ShareGroup ? $recipient->getMembers() : [$recipient] as $user) {
                $people[$user->getId() ?? spl_object_id($user)] = $user;
            }
        }
        $copies = [];
        $spaces = [];
        foreach ($people as $user) {
            $space = $this->of($user, $deposit);
            // Two people of one space (a couple's folder): one copy.
            if (isset($spaces[$space]) || str_starts_with($file, $space.'/')) {
                $spaces[$space] = true;
                continue;
            }
            $spaces[$space] = true;
            $copies[$this->share->copy($file, $space)] = $space;
        }

        return $copies;
    }

    /** The drive's own place for someone no provider knows: drive.spaces_root/<their name>, the team's and theirs. */
    private function made(User $user): string
    {
        $root = FileShare::normalize($this->root);
        $name = FileShare::name(method_exists($user, 'getUsername') && $user->getUsername() ? (string) $user->getUsername() : $user->getUserIdentifier()) ?: 'user-'.$user->getId();
        $path = ltrim($root.'/'.$name, '/');
        if ('' !== $root && !$this->share->isDirectory($root)) {
            $this->share->createDirectory($root);
        }
        if (!$this->share->isDirectory($path)) {
            $this->share->createDirectory($path);
        }
        // Made here: the team's own (not its parent's audience, which may be wider).
        if (null === $this->share->row($path)) {
            $this->share->describe($path, StaffAudience::KEY, null);
        }

        return $path;
    }
}
