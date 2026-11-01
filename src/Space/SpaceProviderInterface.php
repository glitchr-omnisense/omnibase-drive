<?php

namespace Base\Drive\Space;

use Base\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Where a person's space is in the drive: the folder their role gives them - a tenant's, an
 * owner's, a partner's. A bundle that knows its people implements it (omnibase/estate:
 * Locataires/<tenant>, Propriétaires/<owner>, Indivisions/<ownership>), tagged drive.space by
 * autoconfiguration; the providers are asked in their priority's order, the first to answer wins,
 * and nobody answering leaves the drive's own place (Spaces: drive.spaces_root/<name>).
 *
 * A provider makes the folder when it is missing and shares it with the person, read only, as
 * its business does (the drive checks it is shared once more, whatever the provider did).
 */
#[AutoconfigureTag(self::TAG)]
interface SpaceProviderInterface
{
    public const TAG = 'drive.space';

    /** The person's space, made if missing, reused otherwise: its path from the drive's root - null when they are none of this provider's people. */
    public function spaceOf(User $user): ?string;
}
