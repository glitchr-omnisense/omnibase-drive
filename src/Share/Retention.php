<?php

namespace Base\Drive\Share;

use Base\Drive\Entity\RetentionEvent;
use Base\Drive\Repository\RetentionEventRepository;
use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The retention lock (drive.retention, off by default): with it on, every folder of the drive
 * and what it holds is kept - nothing deleted, overwritten, renamed nor moved, by anyone, the team
 * included (FileShare refuses it, ShareVoter's SHARE_ALTER says no). Adding files stays open.
 *
 * Only the superadmin (drive.retention_role) lifts it, folder by folder - until it is put back,
 * or for a while (a duration, an end date) -, and puts it back; each lift and each lock is a line
 * of the log (Entity\RetentionEvent: who, when, which folder, until when, why - a reason is
 * required), never changed nor removed. A folder is as the nearest of itself and its folders
 * whose latest line says something: a lock, or a lift still running; a lift whose date has passed
 * says nothing any more - the lock is back by itself, read so when asked, with no task to run.
 */
class Retention
{
    /** @var array<string, RetentionEvent>|null each folder's latest line */
    private ?array $latest = null;
    /** @var list<RetentionEvent>|null */
    private ?array $events = null;

    public function __construct(
        private readonly RetentionEventRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%drive.retention%')] private readonly bool $enabled = false,
        #[Autowire('%drive.retention_role%')] private readonly string $role = 'ROLE_SUPERADMIN',
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** Who lifts and puts back the lock: the superadmin's role. */
    public function role(): string
    {
        return $this->role;
    }

    /** Kept as it is: retention on, and no lift running for it or the folder that decides for it. */
    public function isLocked(string $path): bool
    {
        return $this->enabled && null === $this->lift($path);
    }

    /** @throws LockedException when the path is kept */
    public function guard(string $path): void
    {
        if ($this->isLocked($path)) {
            throw new LockedException($path);
        }
    }

    /** Kept, or holding something locked again on its own: a folder goes (deleted, moved) only with all it holds. */
    public function isLockedTree(string $path): bool
    {
        if ($this->isLocked($path)) {
            return true;
        }
        foreach (array_keys($this->latest()) as $under) {
            if ($under !== $path && str_starts_with($under, '' === $path ? '' : $path.'/') && $this->isLocked($under)) {
                return true;
            }
        }

        return false;
    }

    /** @throws LockedException when isLockedTree() */
    public function guardTree(string $path): void
    {
        if ($this->isLockedTree($path)) {
            throw new LockedException($path);
        }
    }

    /**
     * The lift a path is open by - its own, or its nearest folder's -, null when it is locked
     * (a lock said there, or nothing said up to the root).
     */
    public function lift(string $path): ?RetentionEvent
    {
        $latest = $this->latest();
        $now = $this->now();
        for ($at = $path; ; $at = str_contains($at, '/') ? substr($at, 0, strrpos($at, '/')) : '') {
            $event = $latest[$at] ?? null;
            if (null !== $event && !$event->isLift()) {
                return null;
            }
            if (null !== $event && $event->liftsOn($now)) {
                return $event;
            }
            if ('' === $at) {
                return null;
            }
        }
    }

    /**
     * A folder's lock lifted, until it is put back ($until null) or until a moment to come.
     *
     * @throws \InvalidArgumentException without retention, without a reason, or with a moment gone
     */
    public function liftFor(string $path, ?User $by, string $reason, ?\DateTimeImmutable $until = null): RetentionEvent
    {
        if (!$this->enabled) {
            throw new \InvalidArgumentException('The drive keeps no retention lock.');
        }
        if (null !== $until && $until <= $this->now()) {
            throw new \InvalidArgumentException('A lift ends in the future.');
        }

        return $this->record(new RetentionEvent($path, RetentionEvent::LIFT, $reason, $by, $this->now(), $until));
    }

    /**
     * A folder's lock put back - also inside a folder lifted, for it and what it holds.
     *
     * @throws \InvalidArgumentException without retention, or without a reason
     */
    public function lock(string $path, ?User $by, string $reason): RetentionEvent
    {
        if (!$this->enabled) {
            throw new \InvalidArgumentException('The drive keeps no retention lock.');
        }

        return $this->record(new RetentionEvent($path, RetentionEvent::LOCK, $reason, $by, $this->now()));
    }

    /**
     * What the log says of a folder, newest first: its own lines, its folders' (which decide for
     * it) and those of the folders under it - the root's is the whole log.
     *
     * @return list<RetentionEvent>
     */
    public function log(string $path): array
    {
        $lines = array_values(array_filter($this->events(), fn (RetentionEvent $event) => '' === $path || '' === $event->getPath()
            || $event->getPath() === $path || str_starts_with($path, $event->getPath().'/') || str_starts_with($event->getPath(), $path.'/')));

        return array_reverse($lines);
    }

    /** Now, by the application's clock when it has one (a test's, frozen). */
    public function now(): \DateTimeImmutable
    {
        return $this->clock?->now() ?? new \DateTimeImmutable();
    }

    private function record(RetentionEvent $event): RetentionEvent
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
        $this->events = $this->latest = null;

        return $event;
    }

    /** @return list<RetentionEvent> */
    private function events(): array
    {
        return $this->events ??= $this->enabled ? $this->repository->log() : [];
    }

    /** @return array<string, RetentionEvent> */
    private function latest(): array
    {
        if (null === $this->latest) {
            $this->latest = [];
            foreach ($this->events() as $event) {
                $this->latest[$event->getPath()] = $event;
            }
        }

        return $this->latest;
    }
}
