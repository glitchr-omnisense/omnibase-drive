<?php

namespace Base\Drive\Entity;

use Base\Drive\Repository\ShareDownloadRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * How many times a file of the drive was downloaded (Base\Drive\Controller\ShareController) - or a
 * folder, as a zip, or the old downloads of versions no longer there (drive:count). A number
 * per path and when it last grew: nothing of who downloaded it.
 */
#[ORM\Entity(repositoryClass: ShareDownloadRepository::class)]
#[ORM\Table(name: 'shareDownload')]
class ShareDownload
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 512, unique: true)]
    private string $path = '';

    #[ORM\Column]
    private int $count = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAt = null;

    public function __construct(string $path = '', int $count = 0)
    {
        $this->path = $path;
        $this->count = $count;
    }

    public function getId(): ?int { return $this->id; }
    public function getPath(): string { return $this->path; }
    public function getCount(): int { return $this->count; }
    public function setCount(int $count): self { $this->count = max(0, $count); return $this; }
    public function getLastAt(): ?\DateTimeImmutable { return $this->lastAt; }
}
