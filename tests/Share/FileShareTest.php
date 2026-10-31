<?php

namespace Tests\Base\Drive\Share;

use Base\Drive\Share\FileShare;
use Tests\Base\Drive\DriveTestCase;

final class FileShareTest extends DriveTestCase
{
    public function testAPathNeverLeadsOutOfTheDriveNorToSomethingHidden(): void
    {
        self::assertSame('a/b', FileShare::normalize('/a//b/'));
        self::assertSame('', FileShare::normalize(''));
        foreach (['../etc', 'a/../b', 'a/.git/config', ".hidden", "a\x00b"] as $path) {
            try {
                FileShare::normalize($path);
                self::fail($path.' was accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame('passwd', FileShare::name('../../etc/passwd'));
        self::assertSame('a b.pdf', FileShare::name('a:b.pdf'));
    }

    public function testAFolderListsItsFoldersFirstAndNothingHidden(): void
    {
        $this->storage->write('docs/b.txt', 'b');
        $this->storage->write('docs/A.txt', 'a');
        $this->storage->write('docs/.git/config', 'secret');
        $this->storage->createDirectory('docs/z-folder');

        $entries = $this->share()->entries('docs');

        self::assertSame(['z-folder', 'A.txt', 'b.txt'], array_column($entries, 'name'));
        self::assertTrue($entries[0]['directory']);
        self::assertSame('txt', $entries[1]['extension']);
    }

    public function testAPathIsForItsNearestFolderThatSaysAndTheDefaultAboveThem(): void
    {
        $this->storage->write('open/inner/file.txt', 'x');
        $this->row('open', 'readers');

        self::assertSame('readers', $this->share()->audience('open/inner/file.txt'));
        self::assertSame('staff', $this->share()->audience('elsewhere'), 'private until a folder says otherwise');
        self::assertSame('readers', $this->share('readers')->audience('elsewhere'), 'a site that opens its root');
    }

    public function testListedAndTagsComeFromTheFoldersAbove(): void
    {
        $this->row('public', 'readers', true)->setTags(['Archive']);
        $this->row('public/private', 'readers', false)->setTags(['archive', '2026']);

        $share = $this->share();
        self::assertTrue($share->listed('public/file.txt'));
        self::assertFalse($share->listed('public/private/file.txt'));
        self::assertFalse($share->listed('other'), 'not listed when nothing says');
        self::assertSame(['archive', '2026'], $share->tags('public/private/x'), 'its own, then its folders\' - each once, whatever its case');
    }

    public function testASearchFindsByNameAndTagAccentsAsideOnlyWhatIsAllowed(): void
    {
        $this->storage->write('Locataires/Léa/État des lieux.jpg', 'x');
        $this->storage->write('Locataires/Paul/Etat des lieux.jpg', 'x');
        $this->storage->write('Assurances/contrat.pdf', 'x');
        $this->row('Assurances', 'staff')->setTags(['Habitation']);

        $share = $this->share();
        $found = $share->search('etat lieux', fn (string $path) => str_starts_with($path, 'Locataires/Léa'));
        self::assertSame(['Locataires/Léa/État des lieux.jpg'], array_column($found, 'path'));
        self::assertSame('Locataires/Léa', $found[0]['in']);
        self::assertSame(['Assurances', 'Assurances/contrat.pdf'], array_column($share->search('habitation', fn () => true), 'path'), 'a tag finds its folder and what it holds');
        self::assertSame([], $share->search('  ', fn () => true));
    }

    public function testSomeoneTheRootIsNotForHasTheHighestFoldersTheySeeAsTheirRoot(): void
    {
        $this->storage->write('Locataires/Léa/bail.pdf', 'x');
        $this->storage->write('Locataires/Paul/bail.pdf', 'x');
        $this->storage->createDirectory('Biens/Krutenau');

        $tops = $this->share()->tops(fn (string $path) => str_starts_with($path, 'Locataires/Léa') || str_starts_with($path, 'Biens'));

        self::assertSame(['Biens', 'Léa'], array_column($tops, 'name'));
    }

    public function testAFolderSharedWithSomeoneIsTheirsInItAndUnderIt(): void
    {
        $this->row('Locataires/Léa', 'staff')->setWorkspace(true)->addMember($lea = $this->user(4));
        $this->row('Locataires/Paul', 'staff')->addMember($this->user(5));

        $share = $this->share();
        self::assertTrue($share->isSharedWith('Locataires/Léa/bail.pdf', $lea));
        self::assertFalse($share->isSharedWith('Locataires', $lea));
        self::assertFalse($share->isSharedWith('Locataires/Léa', $this->user(5)));
        self::assertFalse($share->isSharedWith('Locataires/Paul', $this->user(5)), 'a folder that is no workspace shares with nobody');
        self::assertSame('Locataires/Léa', $share->workspace('Locataires/Léa/sub/file')?->getPath());
    }

    public function testAZipHoldsOnlyWhatTheViewerMaySee(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ext-zip');
        }
        $this->storage->write('folder/open.txt', 'open');
        $this->storage->write('folder/staff/secret.txt', 'secret');
        $this->storage->write('folder/.hidden', 'hidden');

        $file = $this->share()->zip('folder', fn (string $path) => !str_contains($path, 'staff'));
        $zip = new \ZipArchive();
        $zip->open($file);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($file);

        self::assertSame(['open.txt'], $names);
    }
}
