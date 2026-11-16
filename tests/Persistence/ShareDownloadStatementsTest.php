<?php

namespace Base\Drive\Tests\Persistence;

use Base\Drive\Entity\ShareDownload;
use Base\Drive\Repository\ShareDownloadRepository;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The download counters' statements (Persistence\{Doctrine,Eloquent}\ShareDownloadStatements) give the same rows on
 * both engines: the same repository, the same calls, on SQLite, in the style of omnibridge/eloquent's
 * RepositoryParityTest, on the core's Base\\Testing\\Engines (one SQLite, both engines over it).
 */
final class ShareDownloadStatementsTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return Engines::backends();
    }

    #[DataProvider('backends')]
    public function testTheCountersOfAPathAndWhatLiesUnderIt(string $backend): void
    {
        $engines = Engines::of([ShareDownload::class]);   // fresh rows for each engine: the test writes them
        $downloads = new ShareDownloadRepository($engines->finders($backend));

        foreach (['a/report.pdf', 'a/report.pdf', 'a/b/c.txt', 'a_b/x.txt', 'z.txt'] as $path) {
            $downloads->increment($path);
        }
        self::assertSame(['a/b/c.txt' => 1, 'a/report.pdf' => 2, 'a_b/x.txt' => 1, 'z.txt' => 1], self::sorted($downloads->counts()), 'one row per path, counted');

        $downloads->moveUnder('a', 'docs');
        $downloads->clear();
        self::assertSame(['a_b/x.txt' => 1, 'docs/b/c.txt' => 1, 'docs/report.pdf' => 2, 'z.txt' => 1], self::sorted($downloads->counts()), 'moved under the new path, a_b left (its _ is no wildcard)');

        $downloads->deleteUnder('docs');
        $downloads->clear();
        self::assertSame(['a_b/x.txt' => 1, 'z.txt' => 1], self::sorted($downloads->counts()), 'gone with the folder');
    }

    /** @param array<string, int> $counts */
    private static function sorted(array $counts): array
    {
        ksort($counts);

        return $counts;
    }
}
