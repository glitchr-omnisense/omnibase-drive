<?php

namespace Tests\Base\Drive\Space;

use Base\Drive\Entity\ShareGroup;
use Base\Drive\Share\FileShare;
use Base\Drive\Space\SpaceProviderInterface;
use Base\Drive\Space\Spaces;
use Base\Entity\User;
use Tests\Base\Drive\DriveTestCase;

/**
 * A file added to the drive and shared on upload with named people and groups: a copy in each
 * one's space - the one a provider knows (a tenant's) or the drive's own place -, made the first
 * time, reused after, shared with them read only unless the uploader lets them bring files.
 */
final class SpacesTest extends DriveTestCase
{
    private FileShare $share;

    protected function setUp(): void
    {
        parent::setUp();
        $this->share = $this->share();
    }

    public function testAnUploadSharedWithAPersonAndAGroupGoesIntoTheirSpacesMadeThenReused(): void
    {
        $lea = $this->user(4, 'lea');
        $tom = $this->user(5, 'tom');
        $ines = $this->user(6, 'ines');
        $flat = (new ShareGroup('Colocation Krutenau'))->addMember($tom)->addMember($ines);
        $this->storage->write('Biens/Krutenau/DPE.pdf', '%PDF dpe');
        $this->storage->write('Biens/Krutenau/Règlement.pdf', '%PDF règlement');
        $spaces = new Spaces($this->share, [$this->tenants(['lea' => 'Locataires/Léa Muller'])], 'Espaces');

        $copies = $spaces->deliver('Biens/Krutenau/DPE.pdf', [$lea, $flat]);

        self::assertSame([
            'Locataires/Léa Muller/DPE.pdf' => 'Locataires/Léa Muller',
            'Espaces/tom/DPE.pdf' => 'Espaces/tom',
            'Espaces/ines/DPE.pdf' => 'Espaces/ines',
        ], $copies, 'the provider\'s space for the one it knows, the drive\'s own place for the others, each of the group\'s people');
        self::assertSame('%PDF dpe', $this->storage->read('Espaces/tom/DPE.pdf'));
        self::assertTrue($this->storage->fileExists('Biens/Krutenau/DPE.pdf'), 'the file stays where it was added');
        foreach (['Locataires/Léa Muller' => $lea, 'Espaces/tom' => $tom, 'Espaces/ines' => $ines] as $space => $user) {
            self::assertTrue($this->share->isSharedWith($space.'/DPE.pdf', $user), $space.' is shared with its person');
            self::assertFalse($this->share->row($space)->allowsDeposit(), 'read only');
        }
        self::assertFalse($this->share->isSharedWith('Espaces/tom', $ines), 'each one\'s space is theirs alone');
        self::assertSame('staff', $this->share->audience('Espaces/tom'), 'the team\'s, not the root\'s audience');

        // Again, with another file and the same people: the same spaces, nothing made twice.
        $before = $this->share->directories();
        $copies = $spaces->deliver('Biens/Krutenau/Règlement.pdf', [$tom, $flat, $lea]);
        self::assertSame(['Espaces/tom/Règlement.pdf', 'Espaces/ines/Règlement.pdf', 'Locataires/Léa Muller/Règlement.pdf'], array_keys($copies), 'each person once, however often named');
        self::assertSame($before, $this->share->directories(), 'the spaces reused');

        // The same file a second time: beside the first, never over it.
        self::assertSame(['Espaces/tom/DPE (2).pdf'], array_keys($spaces->deliver('Biens/Krutenau/DPE.pdf', [$tom])));
    }

    public function testTheUploaderLetsThemBringFilesAndAFileInTheirSpaceIsNotCopiedThere(): void
    {
        $lea = $this->user(4, 'lea');
        $this->storage->write('Locataires/Léa Muller/Bail.pdf', '%PDF bail');
        $spaces = new Spaces($this->share, [$this->tenants(['lea' => 'Locataires/Léa Muller'])]);

        self::assertSame([], $spaces->deliver('Locataires/Léa Muller/Bail.pdf', [$lea], deposit: true), 'already in her space');
        self::assertTrue($this->share->isSharedWith('Locataires/Léa Muller/Bail.pdf', $lea));
        self::assertTrue($this->share->row('Locataires/Léa Muller')->allowsDeposit(), 'she may bring files');

        $spaces->deliver('Locataires/Léa Muller/Bail.pdf', [$lea]);
        self::assertTrue($this->share->row('Locataires/Léa Muller')->allowsDeposit(), 'a right given is not taken back by sharing again');
    }

    /** @param array<string, string> $known a provider knowing these people (name => space), as omnibase/estate knows its tenants */
    private function tenants(array $known): SpaceProviderInterface
    {
        return new class($this->share, $known) implements SpaceProviderInterface {
            public function __construct(private readonly FileShare $share, private readonly array $known)
            {
            }

            public function spaceOf(User $user): ?string
            {
                $path = $this->known[$user->getUserIdentifier()] ?? null;
                if (null !== $path && !$this->share->isDirectory($path)) {
                    $this->share->createDirectory($path);
                }

                return $path;
            }
        };
    }
}
