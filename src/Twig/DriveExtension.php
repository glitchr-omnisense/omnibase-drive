<?php

namespace Base\Drive\Twig;

use Base\Drive\Share\FileShare;
use Base\Drive\Share\PersonalTags;
use Base\Drive\Share\TagColors;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * For a site's templates: drive_tag_color(tag), a team's tag's colour (Share\TagColors);
 * drive_my_tag_color(tag), the colour of a tag of one's own (Share\PersonalTags); path|drive_slug,
 * a path as the drive's addresses read it (drive.slugs: its slugs, otherwise itself) - for
 * path('app_share', {path: entry.path|drive_slug}).
 */
final class DriveExtension extends AbstractExtension
{
    public function __construct(
        private readonly TagColors $colors,
        private readonly PersonalTags $personal,
        #[Autowire('%drive.slugs%')] private readonly bool $slugs = false,
    ) {
    }

    public function getFilters(): array
    {
        return [new TwigFilter('drive_slug', fn (?string $path) => $this->slugs ? FileShare::slug((string) $path) : (string) $path)];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('drive_tag_color', $this->colors->of(...)),
            new TwigFunction('drive_my_tag_color', $this->personal->color(...)),
        ];
    }
}
