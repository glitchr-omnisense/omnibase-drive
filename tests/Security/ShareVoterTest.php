<?php

namespace Tests\Base\Drive\Security;

use Base\Drive\Security\Voter\ShareVoter;
use Base\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Tests\Base\Drive\DriveTestCase;

final class ShareVoterTest extends DriveTestCase
{
    public function testTheStaffSeeAndManageEverything(): void
    {
        $voter = $this->voter(staff: true);
        $token = $this->token($this->user(1));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, 'anything', [ShareVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, [ShareVoter::MANAGE]));
    }

    public function testAFolderIsForItsAudienceAndThePeopleItIsSharedWith(): void
    {
        $this->row('open', 'readers');
        $this->row('Locataires', 'staff');
        $this->row('Locataires/Léa', 'staff')->setWorkspace(true)->addMember($lea = $this->user(4));
        $voter = $this->voter();
        $anonymous = new NullToken();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($anonymous, 'open/file', [ShareVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($anonymous, 'Locataires', [ShareVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($anonymous, 'nowhere', [ShareVoter::VIEW]), 'private by default');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($lea), 'Locataires/Léa/bail.pdf', [ShareVoter::VIEW]), 'shared with her: hers whoever else it is for');
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($lea), 'Locataires', [ShareVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($this->user(5)), 'Locataires/Léa', [ShareVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($lea), 'Locataires/Léa', [ShareVoter::WORK]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($lea), null, [ShareVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->token($lea), null, [ShareVoter::VIEW]), 'a path is a string');
    }

    private function voter(bool $staff = false): ShareVoter
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($staff);

        return new ShareVoter($this->share(), self::audiences(), $security);
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', []);
    }
}
