<?php

namespace Base\Drive\Entity;

use Base\Drive\Repository\SharedPathRepository;
use Base\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * What the team says of a folder or a file of the drive (Base\Drive\Share\FileShare): who it is
 * for (an audience's key: Base\Drive\Audience), a few words shown above its list, its tags (what
 * is under it has them too), whether it is listed. A folder may be a workspace: its people
 * (members: they see it whoever else it is for, and what is "for its members" is for them) and
 * its tasks. The files themselves are on the storage; a path without a row here is as its nearest
 * parent with one - the root as drive.default_audience says.
 *
 * Its table keeps the name the sites created it under (sharedPath, sharedPathMember): no data moves.
 * Its people are accounts of the application's App\Entity\User, as the core's own entities.
 */
#[ORM\Entity(repositoryClass: SharedPathRepository::class)]
#[ORM\Table(name: 'sharedPath')]
class SharedPath
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** The path from the drive's root, without slashes at its ends: "" is the root. */
    #[ORM\Column(length: 512, unique: true)]
    private string $path = '';

    /** An audience's key (Base\Drive\Audience\AudienceInterface::key()): "readers", "staff"... */
    #[ORM\Column(length: 16)]
    private string $audience = 'staff';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var list<string>|null its tags, as typed: "leblogdekzl", "Raspberry Pi" */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = null;

    /**
     * Listed on the drive's root - and open to search engines -, or opened by its link only;
     * null: as its folder (none says: not listed).
     */
    #[ORM\Column(nullable: true)]
    private ?bool $listed = null;

    /** A workspace: a folder with its people and its tasks. */
    #[ORM\Column(options: ['default' => false])]
    private bool $workspace = false;

    /** Its people may bring files into it (a partner's invoices); otherwise they only read it. */
    #[ORM\Column(options: ['default' => false])]
    private bool $deposit = false;

    /** @var Collection<int, User> its people: they see it whoever else it is for; what is "for its members" is for them alone */
    #[ORM\ManyToMany(targetEntity: 'App\Entity\User')]
    #[ORM\JoinTable(name: 'sharedPathMember')]
    private Collection $members;

    /** @var Collection<int, ShareGroup> the groups it is shared with: each one's people are among its people */
    #[ORM\ManyToMany(targetEntity: ShareGroup::class)]
    #[ORM\JoinTable(name: 'sharedPathGroup')]
    private Collection $groups;

    /** @var Collection<int, ShareTask> its tasks, in their order */
    #[ORM\OneToMany(targetEntity: ShareTask::class, mappedBy: 'workspace', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['done' => 'ASC', 'position' => 'ASC', 'id' => 'ASC'])]
    private Collection $tasks;

    public function __construct(string $path = '', string $audience = 'staff')
    {
        $this->path = $path;
        $this->audience = $audience;
        $this->members = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->tasks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self { $this->path = $path; return $this; }
    public function getAudience(): string { return $this->audience; }
    public function setAudience(string $audience): self { $this->audience = $audience; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = '' !== trim((string) $description) ? trim((string) $description) : null; return $this; }

    /** @return list<string> */
    public function getTags(): array { return $this->tags ?? []; }

    /** @param iterable<string> $tags each trimmed, the same one once (whatever its case) */
    public function setTags(iterable $tags): self
    {
        $kept = [];
        foreach ($tags as $tag) {
            $tag = trim(preg_replace('/\s+/u', ' ', (string) $tag));
            if ('' !== $tag && !\in_array(mb_strtolower($tag), array_map('mb_strtolower', $kept), true)) {
                $kept[] = mb_substr($tag, 0, 48);
            }
        }
        $this->tags = $kept ?: null;

        return $this;
    }

    public function isListed(): ?bool { return $this->listed; }
    public function setListed(?bool $listed): self { $this->listed = $listed; return $this; }

    public function isWorkspace(): bool { return $this->workspace; }
    public function setWorkspace(bool $workspace): self { $this->workspace = $workspace; return $this; }

    public function allowsDeposit(): bool { return $this->deposit; }
    public function setDeposit(bool $deposit): self { $this->deposit = $deposit; return $this; }

    /** @return Collection<int, User> */
    public function getMembers(): Collection { return $this->members; }
    public function addMember(User $user): self { if (!$this->members->exists(fn ($k, User $m) => $m === $user || (null !== $m->getId() && $m->getId() === $user->getId()))) { $this->members->add($user); } return $this; }
    public function removeMember(User $user): self { foreach ($this->members->toArray() as $member) { if ($member->getId() === $user->getId()) { $this->members->removeElement($member); } } return $this; }
    /** One of its people: by name, or through a group it is shared with. */
    public function hasMember(User $user): bool
    {
        return $this->members->exists(fn ($k, User $m) => $m === $user || (null !== $m->getId() && $m->getId() === $user->getId()))
            || $this->groups->exists(fn ($k, ShareGroup $group) => $group->hasMember($user));
    }

    /** @return Collection<int, ShareGroup> */
    public function getGroups(): Collection { return $this->groups; }
    public function addGroup(ShareGroup $group): self { if (!$this->groups->contains($group)) { $this->groups->add($group); } return $this; }
    public function removeGroup(ShareGroup $group): self { $this->groups->removeElement($group); return $this; }

    /** @return Collection<int, ShareTask> */
    public function getTasks(): Collection { return $this->tasks; }
    public function addTask(ShareTask $task): self { if (!$this->tasks->contains($task)) { $this->tasks->add($task); $task->setWorkspace($this); } return $this; }
    public function removeTask(ShareTask $task): self { $this->tasks->removeElement($task); return $this; }
}
