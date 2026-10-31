<?php

namespace Base\Drive\People;

/**
 * Whom a folder of the drive may be shared with, by name and what they are to the site: groups of
 * accounts (user id => label). The bundle's AccountPeople lists every account; a site says who its
 * people are with its own implementation and #[AsAlias(PeopleInterface::class)] - omnibase/estate's
 * tenants, associates, contractors and team for a property company. Each group's name is a
 * translation key of the messages domain: share.people_group.<group>.
 */
interface PeopleInterface
{
    /** @return array<string, array<int, string>> each group's people, user id => name and what they are; empty groups left out */
    public function grouped(): array;

    /** @return array<int, string> user id => their name and what they are, every group together */
    public function names(): array;
}
