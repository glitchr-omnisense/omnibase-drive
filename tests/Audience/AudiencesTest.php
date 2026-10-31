<?php

namespace Tests\Base\Drive\Audience;

use Base\Drive\Audience\AudienceInterface;
use Base\Drive\Share\FileShare;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\Base\Drive\DriveTestCase;

final class AudiencesTest extends DriveTestCase
{
    public function testASitesAudienceTakesItsPlaceBeforeTheStaffWhoCloseTheList(): void
    {
        $audiences = self::audiences([$this->associates()]);

        self::assertSame(['readers', 'users', 'members', 'associates', 'staff'], $audiences->keys());
        self::assertSame('fa-solid fa-people-group', $audiences->get('associates')->icon());
        self::assertSame('share.audience.readers', $audiences->get('readers')->label());
    }

    public function testAKeyNobodyAnswersToAnyMoreIsTheStaffs(): void
    {
        $audiences = self::audiences();

        self::assertSame('staff', $audiences->get('customers')->key(), 'a site that dropped an audience keeps its folders private');
        self::assertNull($audiences->find('customers'));
        self::assertNull($audiences->find(null));
        self::assertSame('members', $audiences->find('members')?->key());
    }

    public function testEachBuiltInAudienceLetsInWhomItSays(): void
    {
        $this->row('space', 'members')->setWorkspace(true)->addMember($member = $this->user(7));
        $share = $this->share();
        $audiences = self::audiences();
        $someone = $this->createStub(UserInterface::class);

        self::assertTrue($audiences->get('readers')->lets(null, 'x', $share));
        self::assertFalse($audiences->get('users')->lets(null, 'x', $share));
        self::assertTrue($audiences->get('users')->lets($someone, 'x', $share));
        self::assertTrue($audiences->get('members')->lets($member, 'space/file', $share));
        self::assertFalse($audiences->get('members')->lets($this->user(8), 'space/file', $share));
        self::assertFalse($audiences->get('staff')->lets($member, 'x', $share));
    }

    private function associates(): AudienceInterface
    {
        return new class implements AudienceInterface {
            public function key(): string { return 'associates'; }
            public function label(): string { return 'share.audience.associates'; }
            public function icon(): string { return 'fa-solid fa-people-group'; }
            public function lets(?UserInterface $user, string $path, FileShare $share): bool { return null !== $user; }
        };
    }
}
