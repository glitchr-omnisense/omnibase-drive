<?php

namespace Base\Drive\Twig;

use Base\Drive\Share\TagColors;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** drive_tag_color(tag): a tag's colour (Share\TagColors), for a site's templates. */
final class DriveExtension extends AbstractExtension
{
    public function __construct(private readonly TagColors $colors)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('drive_tag_color', $this->colors->of(...))];
    }
}
