<?php

namespace Tests\Base\Drive\Share;

use Base\Drive\Entity\RetentionEvent;
use Base\Drive\Security\Voter\ShareVoter;
use Base\Drive\Share\LockedException;
use Base\Drive\Share\Retention;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Tests\Base\Drive\DriveTestCase;

/**
 * The retention lock (drive.retention): nothing kept is deleted, overwritten, renamed nor moved,
 * by anyone; the superadmin alone lifts it for a folder - until put back, or for a while - and puts
 * it back, each time logged with who, when, until when and why; a lift whose time is up is a lock
 * again by itself.
 */
final class RetentionTest extends DriveTestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new MockClock('2026-11-01 10:00:00');
        $this->storage->write('Locataires/Léa/Bail.pdf', '%PDF bail');
        $this->storage->write('Locataires/Léa/État des lieux.pdf', '%PDF edl');
        $this->storage->write('Locataires/Paul/Bail.pdf', '%PDF bail paul');
    }

    public function testALockedFolderRefusesDeleteOverwriteRenameAndMove(): void
    {
        $share = $this->share(retention: $this->retention(clock: $this->clock));

        foreach ([
            'delete' => fn () => $share->delete('Locataires/Léa/Bail.pdf'),
            'delete a folder' => fn () => $share->delete('Locataires/Léa'),
            'overwrite' => fn () => $share->write('Locataires/Léa/Bail.pdf', self::stream('autre')),
            'rename' => fn () => $share->move('Locataires/Léa/Bail.pdf', 'Locataires/Léa', 'Bail signé.pdf'),
            'move' => fn () => $share->move('Locataires/Léa/Bail.pdf', 'Locataires/Paul'),
        ] as $what => $refused) {
            try {
                $refused();
                self::fail($what.' was let through');
            } catch (LockedException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame('%PDF bail', $this->storage->read('Locataires/Léa/Bail.pdf'), 'left as it was');
        self::assertTrue($share->isLocked('Locataires/Léa/Bail.pdf'));

        // Adding stays open: a new file, a copy beside one of the same name.
        $share->write('Locataires/Léa/Quittance.pdf', self::stream('%PDF quittance'));
        self::assertSame('Locataires/Léa/Bail (2).pdf', $share->copy('Locataires/Paul/Bail.pdf', 'Locataires/Léa'));

        // The team included: SHARE_ALTER says no to the staff as to anyone.
        $voter = $this->voter($share, $retention = $this->retention(clock: $this->clock), ['ROLE_STAFF']);
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token(), 'Locataires/Léa/Bail.pdf', [ShareVoter::ALTER]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token(), 'Locataires/Léa/Bail.pdf', [ShareVoter::MANAGE]), 'they still manage it otherwise');

        // Off (the default): nothing is kept.
        $this->share(retention: $this->retention(false))->delete('Locataires/Paul/Bail.pdf');
        self::assertFalse($this->storage->fileExists('Locataires/Paul/Bail.pdf'));
    }

    public function testTheSuperadminLiftsAFolderWithItsLogAndNobodyElseDoes(): void
    {
        $retention = $this->retention(clock: $this->clock);
        $share = $this->share(retention: $retention);
        $superadmin = $this->user(1, 'glitchr');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter($share, $retention, ['ROLE_SUPERADMIN', 'ROLE_STAFF'])->vote($this->token($superadmin), 'Locataires/Léa', [ShareVoter::RETAIN]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter($share, $retention, ['ROLE_STAFF', 'ROLE_ADMIN'])->vote($this->token(), 'Locataires/Léa', [ShareVoter::RETAIN]), 'the team does not');
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter($share, $this->retention(false), ['ROLE_SUPERADMIN'])->vote($this->token($superadmin), 'Locataires/Léa', [ShareVoter::RETAIN]), 'nothing to lift without retention');

        try {
            $retention->liftFor('Locataires/Léa', $superadmin, '  ');
            self::fail('a lift without a reason');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $retention->liftFor('Locataires/Léa', $superadmin, 'Erreur de dépôt à corriger');
        self::assertFalse($share->isLocked('Locataires/Léa/État des lieux.pdf'), 'the folder and what it holds');
        self::assertTrue($share->isLocked('Locataires/Paul/Bail.pdf'), 'not its neighbour');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter($share, $retention, ['ROLE_STAFF'])->vote($this->token(), 'Locataires/Léa/État des lieux.pdf', [ShareVoter::ALTER]));
        $share->move('Locataires/Léa/État des lieux.pdf', 'Locataires/Léa', 'État des lieux d\'entrée.pdf');
        $share->delete('Locataires/Léa/Bail.pdf');
        self::assertSame(['État des lieux d\'entrée.pdf'], array_column($share->entries('Locataires/Léa'), 'name'));

        $this->clock->sleep(60);
        $retention->lock('Locataires/Léa', $superadmin, 'Corrigé');
        self::assertTrue($share->isLocked('Locataires/Léa/État des lieux d\'entrée.pdf'), 'put back');

        $log = $retention->log('Locataires/Léa');
        self::assertSame([RetentionEvent::LOCK, RetentionEvent::LIFT], array_map(fn (RetentionEvent $e) => $e->getAction(), $log), 'newest first');
        self::assertSame(['Corrigé', 'Erreur de dépôt à corriger'], array_map(fn (RetentionEvent $e) => $e->getReason(), $log));
        self::assertSame('glitchr', $log[1]->getUserName());
        self::assertSame('2026-11-01 10:00', $log[1]->getAt()->format('Y-m-d H:i'));
        self::assertNull($log[1]->getUntil(), 'until put back');
        self::assertSame([], $retention->log('Locataires/Paul'), 'the log of a folder: its own lines, above and under it');
        self::assertCount(2, $retention->log(''), 'the root\'s: the whole log');
    }

    public function testALiftForAWhileExpiresByItself(): void
    {
        $retention = $this->retention(clock: $this->clock);
        $share = $this->share(retention: $retention);

        try {
            $retention->liftFor('Locataires/Léa', null, 'Trop tard', new \DateTimeImmutable('2026-11-01 09:00:00'));
            self::fail('a lift ending in the past');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $lift = $retention->liftFor('Locataires/Léa', $this->user(1, 'glitchr'), 'Classement', $this->clock->now()->modify('+2 hours'));
        self::assertSame($lift, $retention->lift('Locataires/Léa/Bail.pdf'));
        self::assertFalse($share->isLocked('Locataires/Léa/Bail.pdf'));

        $this->clock->sleep(3 * 3600);
        self::assertTrue($share->isLocked('Locataires/Léa/Bail.pdf'), 'back by itself once its time is up');
        self::assertNull($retention->lift('Locataires/Léa'));
        $this->expectException(LockedException::class);
        $share->delete('Locataires/Léa/Bail.pdf');
    }

    public function testALockPutBackInsideALiftedFolderKeepsItAndTheFolderGoesOnlyWithAllItHolds(): void
    {
        $retention = $this->retention(clock: $this->clock);
        $share = $this->share(retention: $retention);
        $retention->liftFor('Locataires', null, 'Réorganisation');
        $retention->lock('Locataires/Léa', null, 'Dossier en litige');

        self::assertFalse($share->isLocked('Locataires/Paul/Bail.pdf'));
        self::assertTrue($share->isLocked('Locataires/Léa/Bail.pdf'));
        $share->move('Locataires/Paul', '', 'Paul');
        self::assertTrue($this->storage->fileExists('Paul/Bail.pdf'), 'out of the lifted folder');
        $this->expectException(LockedException::class);
        $share->delete('Locataires');
    }

    private static function stream(string $contents)
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /** @param list<string> $roles what the one asking is granted */
    private function voter($share, Retention $retention, array $roles): ShareVoter
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(fn (string $role) => \in_array($role, $roles, true));

        return new ShareVoter($share, self::audiences(), $security, 'ROLE_STAFF', $retention);
    }

    private function token($user = null): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user ?? $this->user(2, 'bankdash2'), 'main', []);
    }
}
