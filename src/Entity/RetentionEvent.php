<?php

namespace Base\Drive\Entity;

use Base\Drive\Repository\RetentionEventRepository;
use Base\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * One line of the retention log (drive.retention, Base\Drive\Share\Retention): a folder's lock
 * lifted - until it is put back, or until a date -, or put back, by whom, when and why. The log
 * is only ever added to: a folder's state is its latest line (a lift that is still running, or
 * locked), a lift whose date has passed being a lock again by itself.
 */
#[ORM\Entity(repositoryClass: RetentionEventRepository::class)]
#[ORM\Table(name: 'shareRetention')]
#[ORM\Index(name: 'share_retention_path', columns: ['path'])]
class RetentionEvent
{
    public const LIFT = 'lift';
    public const LOCK = 'lock';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** The folder, from the drive's root ("" is the root). */
    #[ORM\Column(length: 512)]
    private string $path;

    /** lift or lock. */
    #[ORM\Column(length: 8)]
    private string $action;

    /** A lift's end: the lock back by itself then; null, until someone puts it back. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $until;

    #[ORM\Column(type: 'text')]
    private string $reason;

    /** Who did it: an account of drive.user_class; null from the command line, or once the account is gone. */
    #[ORM\ManyToOne(targetEntity: 'App\Entity\User')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    /** Its name when it was done, kept should the account go. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userName;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $at;

    public function __construct(string $path, string $action, string $reason, ?User $user, \DateTimeImmutable $at, ?\DateTimeImmutable $until = null)
    {
        if (!\in_array($action, [self::LIFT, self::LOCK], true)) {
            throw new \InvalidArgumentException(sprintf('"%s" is neither a lift nor a lock.', $action));
        }
        if ('' === trim($reason)) {
            throw new \InvalidArgumentException('A lift or a lock says why.');
        }
        $this->path = $path;
        $this->action = $action;
        $this->reason = trim($reason);
        $this->user = $user;
        $this->userName = null !== $user ? mb_substr((string) $user, 0, 180) : null;
        $this->at = $at;
        $this->until = self::LIFT === $action ? $until : null;
    }

    public function getId(): ?int { return $this->id; }
    public function getPath(): string { return $this->path; }
    public function getAction(): string { return $this->action; }
    public function isLift(): bool { return self::LIFT === $this->action; }
    public function getUntil(): ?\DateTimeImmutable { return $this->until; }
    public function getReason(): string { return $this->reason; }
    public function getUser(): ?User { return $this->user; }
    public function getUserName(): ?string { return $this->userName; }
    public function getAt(): \DateTimeImmutable { return $this->at; }

    /** A lift still running at that moment: until it is put back, or until a date not reached yet. */
    public function liftsOn(\DateTimeImmutable $now): bool
    {
        return self::LIFT === $this->action && (null === $this->until || $this->until > $now);
    }
}
