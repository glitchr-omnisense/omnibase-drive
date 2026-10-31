<?php

namespace Base\Drive\Share;

use Base\Drive\Entity\PersonalTag;
use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The tags each one puts on folders for themselves (drive.personal_tags): a tenant sorting their
 * papers, an owner theirs, nobody else seeing them. Like the team's tags, a folder's apply to what
 * it holds (to filter by). Nothing when the option is off or nobody is signed in; their rows
 * follow a folder moved and go with one deleted (FileShare).
 */
final class PersonalTags
{
    /** @var array<string, list<PersonalTag>>|null the signed-in person's tags by path */
    private ?array $mine = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[Autowire('%drive.personal_tags%')] private readonly bool $enabled = false,
    ) {
    }

    /** Whether the one signed in has tags of their own here. */
    public function enabled(): bool
    {
        return null !== $this->user();
    }

    /** @return list<string> a path's own tags, as typed */
    public function own(string $path): array
    {
        return array_map(fn (PersonalTag $tag) => $tag->getName(), $this->mine()[$path] ?? []);
    }

    /** @return list<string> a path's tags: its own, then its folders', each once */
    public function tags(string $path): array
    {
        $tags = [];
        for ($at = $path; ; $at = str_contains($at, '/') ? substr($at, 0, strrpos($at, '/')) : '') {
            foreach ($this->mine()[$at] ?? [] as $tag) {
                $tags[$tag->getTagKey()] ??= $tag->getName();
            }
            if ('' === $at) {
                return array_values($tags);
            }
        }
    }

    /** @param iterable<string> $names added to a path, each once */
    public function add(string $path, iterable $names): void
    {
        if (!$user = $this->user()) {
            return;
        }
        $have = array_map(fn (PersonalTag $tag) => $tag->getTagKey(), $this->mine()[$path] ?? []);
        foreach ($names as $name) {
            $name = mb_substr(trim($name), 0, 48);
            $key = mb_substr(FileShare::tagKey($name), 0, 48);
            if ('' === $key || \in_array($key, $have, true)) {
                continue;
            }
            $tag = new PersonalTag($user, $path, $name, $key);
            $tag->setColor($this->chosen($key));
            $this->entityManager->persist($tag);
            $have[] = $key;
        }
        $this->entityManager->flush();
        $this->mine = null;
    }

    /** @param iterable<string> $names taken off a path */
    public function remove(string $path, iterable $names): void
    {
        $keys = array_map(FileShare::tagKey(...), [...$names]);
        foreach ($this->mine()[$path] ?? [] as $tag) {
            if (\in_array($tag->getTagKey(), $keys, true)) {
                $this->entityManager->remove($tag);
            }
        }
        $this->entityManager->flush();
        $this->mine = null;
    }

    /** A tag's colour for the one signed in: the one they chose, otherwise one drawn from its name. */
    public function color(string $tag): string
    {
        return $this->chosen(FileShare::tagKey($tag)) ?? TagColors::drawn($tag);
    }

    /** The colour they choose for a tag (#rrggbb) on every folder they gave it, or none to give it back its own. */
    public function choose(string $tag, ?string $color): void
    {
        $color = null !== $color && preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : null;
        $key = FileShare::tagKey($tag);
        foreach ($this->mine() as $tags) {
            foreach ($tags as $one) {
                if ($one->getTagKey() === $key) {
                    $one->setColor($color);
                }
            }
        }
        $this->entityManager->flush();
    }

    /** Everyone's tags under a path moved, under its new path. */
    public function moved(string $from, string $to): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->entityManager->createQuery(sprintf('UPDATE %s t SET t.path = CONCAT(:to, SUBSTRING(t.path, :start)) WHERE t.path = :from OR t.path LIKE :under', PersonalTag::class))
            ->execute(['to' => $to, 'start' => \strlen($from) + 1, 'from' => $from, 'under' => addcslashes($from, '%_').'/%']);
        $this->mine = null;
    }

    /** Everyone's tags under a path deleted, gone with it. */
    public function deleted(string $path): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->entityManager->createQuery(sprintf('DELETE FROM %s t WHERE t.path = :path OR t.path LIKE :under', PersonalTag::class))
            ->execute(['path' => $path, 'under' => addcslashes($path, '%_').'/%']);
        $this->mine = null;
    }

    private function user(): ?User
    {
        $user = $this->enabled ? $this->security->getUser() : null;

        return $user instanceof User ? $user : null;
    }

    private function chosen(string $key): ?string
    {
        foreach ($this->mine() as $tags) {
            foreach ($tags as $tag) {
                if ($tag->getTagKey() === $key && null !== $tag->getColor()) {
                    return $tag->getColor();
                }
            }
        }

        return null;
    }

    /** @return array<string, list<PersonalTag>> */
    private function mine(): array
    {
        if (null === $this->mine) {
            $this->mine = [];
            if ($user = $this->user()) {
                foreach ($this->entityManager->getRepository(PersonalTag::class)->findBy(['user' => $user], ['id' => 'ASC']) as $tag) {
                    $this->mine[$tag->getPath()][] = $tag;
                }
            }
        }

        return $this->mine;
    }
}
