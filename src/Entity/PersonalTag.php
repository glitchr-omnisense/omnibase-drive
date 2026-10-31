<?php

namespace Base\Drive\Entity;

use Base\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * A tag someone put on a folder for themselves (Base\Drive\Share\PersonalTags, drive.personal_tags):
 * theirs alone - nobody else sees it, the team neither -, to sort what they see as they like. Its
 * colour is theirs too, the same on every folder they gave that tag.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sharePersonalTag')]
#[ORM\UniqueConstraint(name: 'share_personal_tag_unique', columns: ['user_id', 'path', 'tag_key'])]
class PersonalTag
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 512)]
    private string $path;

    /** as typed: "À relire" */
    #[ORM\Column(length: 48)]
    private string $name;

    /** the name folded (FileShare::tagKey), the same tag whatever its case or accents */
    #[ORM\Column(name: 'tag_key', length: 48)]
    private string $tagKey;

    /** #rrggbb, or none: one drawn from its name */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    public function __construct(User $user, string $path, string $name, string $tagKey)
    {
        $this->user = $user;
        $this->path = $path;
        $this->name = $name;
        $this->tagKey = $tagKey;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPath(): string { return $this->path; }
    public function getName(): string { return $this->name; }
    public function getTagKey(): string { return $this->tagKey; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self { $this->color = $color; return $this; }
}
