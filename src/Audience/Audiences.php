<?php

namespace Base\Drive\Audience;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Every audience of the drive (AudienceInterface, tagged drive.audience), by key, in the order of
 * « Pour qui »: the bundle's (readers, users, members), the site's, then staff last. A key no
 * audience answers to any more (a site that removed one) is read as the staff's: nobody else sees
 * what was for it.
 */
final class Audiences
{
    /** @var array<string, AudienceInterface>|null */
    private ?array $byKey = null;

    /** @param iterable<AudienceInterface> $audiences */
    public function __construct(
        #[AutowireIterator(AudienceInterface::TAG)] private readonly iterable $audiences,
    ) {
    }

    /** @return array<string, AudienceInterface> */
    public function all(): array
    {
        if (null === $this->byKey) {
            $this->byKey = [];
            foreach ($this->audiences as $audience) {
                $this->byKey[$audience->key()] = $audience;
            }
            // The staff last: the most private choice closes the list.
            if (isset($this->byKey[StaffAudience::KEY])) {
                $staff = $this->byKey[StaffAudience::KEY];
                unset($this->byKey[StaffAudience::KEY]);
                $this->byKey[StaffAudience::KEY] = $staff;
            }
        }

        return $this->byKey;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /** The audience of a key - the staff's for a key nobody answers to. */
    public function get(string $key): AudienceInterface
    {
        return $this->all()[$key] ?? $this->all()[StaffAudience::KEY] ?? new StaffAudience();
    }

    /** The audience of a key typed or posted, or null when there is none of that key. */
    public function find(?string $key): ?AudienceInterface
    {
        return null !== $key ? ($this->all()[$key] ?? null) : null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
