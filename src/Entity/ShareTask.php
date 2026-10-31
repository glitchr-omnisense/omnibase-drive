<?php

namespace Base\Drive\Entity;

use Base\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * A task of a workspace of the drive (SharedPath::$tasks): a few words, done or
 * not - and when, and who wrote it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shareTask')]
class ShareTask
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SharedPath::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SharedPath $workspace = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column]
    private bool $done = false;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    public function __construct(string $title = '', ?User $author = null)
    {
        $this->title = mb_substr(trim($title), 0, 255);
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): ?SharedPath { return $this->workspace; }
    public function setWorkspace(?SharedPath $workspace): self { $this->workspace = $workspace; return $this; }
    public function getTitle(): string { return $this->title; }
    public function isDone(): bool { return $this->done; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getDoneAt(): ?\DateTimeImmutable { return $this->doneAt; }

    /** Done, or back to do. */
    public function toggle(): self
    {
        $this->done = !$this->done;
        $this->doneAt = $this->done ? new \DateTimeImmutable() : null;

        return $this;
    }
}
