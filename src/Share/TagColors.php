<?php

namespace Base\Drive\Share;

use Base\Service\SettingBagInterface;

/**
 * A tag's colour, the same wherever the tag is shown: the one the team chose for it (kept in the
 * site's settings, app.drive.tag_colors, by the tag's key), otherwise one of a few drawn from its
 * name - so that a tag has a colour of its own before anyone chooses.
 */
final class TagColors
{
    private const KEY = 'app.drive.tag_colors';
    public const PALETTE = ['#2a63b3', '#2f8a5b', '#c8312b', '#b7791f', '#7b4bb0', '#0f7c80', '#c2417a', '#5b6672'];

    /** @var array<string, string>|null */
    private ?array $chosen = null;

    public function __construct(private readonly SettingBagInterface $settings)
    {
    }

    public function of(string $tag): string
    {
        $key = FileShare::tagKey($tag);

        return $this->chosen()[$key] ?? self::PALETTE[crc32($key) % \count(self::PALETTE)];
    }

    /** The colour chosen for a tag (#rrggbb), or none to give it back its own. */
    public function choose(string $tag, ?string $color): void
    {
        $key = FileShare::tagKey($tag);
        if ('' === $key) {
            return;
        }
        $chosen = $this->chosen();
        if (null !== $color && preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            $chosen[$key] = strtolower($color);
        } else {
            unset($chosen[$key]);
        }
        $this->settings->set(self::KEY, json_encode($chosen));
        $this->chosen = $chosen;
    }

    /** @return array<string, string> */
    private function chosen(): array
    {
        if (null === $this->chosen) {
            $stored = json_decode((string) $this->settings->getScalar(self::KEY), true);
            $this->chosen = \is_array($stored) ? array_filter($stored, 'is_string') : [];
        }

        return $this->chosen;
    }
}
