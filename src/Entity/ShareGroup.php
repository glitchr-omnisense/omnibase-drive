<?php

namespace Base\Drive\Entity;

use Base\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A group of people the team names - a flatshare's tenants, a couple, a family's heirs - to share
 * folders with all of them at once (SharedPath::$groups): whoever is in it sees what is shared
 * with it, as if shared with them by name; someone added later sees it too, someone taken out no
 * longer does.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shareGroup')]
class ShareGroup
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: 'App\Entity\User')]
    #[ORM\JoinTable(name: 'shareGroupMember')]
    private Collection $members;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->members = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return Collection<int, User> */
    public function getMembers(): Collection { return $this->members; }
    public function addMember(User $user): self { if (!$this->hasMember($user)) { $this->members->add($user); } return $this; }
    public function removeMember(User $user): self { foreach ($this->members->toArray() as $member) { if ($member->getId() === $user->getId()) { $this->members->removeElement($member); } } return $this; }
    public function hasMember(User $user): bool { return $this->members->exists(fn ($k, User $m) => $m === $user || (null !== $m->getId() && $m->getId() === $user->getId())); }

    public function __toString(): string { return $this->name; }
}
