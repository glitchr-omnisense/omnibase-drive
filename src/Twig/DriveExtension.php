<?php

namespace Base\Drive\Twig;

use Base\Drive\Share\PersonalTags;
use Base\Drive\Share\TagColors;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * For a site's templates: drive_tag_color(tag), a team's tag's colour (Share\TagColors);
 * drive_my_tag_color(tag), the colour of a tag of one's own (Share\PersonalTags).
 */
final class DriveExtension extends AbstractExtension
{
    public function __construct(private readonly TagColors $colors, private readonly PersonalTags $personal)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('drive_tag_color', $this->colors->of(...)),
            new TwigFunction('drive_my_tag_color', $this->personal->color(...)),
        ];
    }
}
