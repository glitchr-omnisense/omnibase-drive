<?php

namespace Base\Drive\Repository;

use Base\Database\Exception\UniqueConstraintViolation;
use Base\Database\Repository\Finder\Change;
use Base\Drive\Entity\ShareDownload;
use Base\Database\Repository\Finders;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * The downloads of the shared paths: counted, renamed and deleted in the database (updateBy(), deleteBy()),
 * at once, whatever else is counting at the same time; read by a finder.
 *
 * @method ShareDownload|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShareDownload|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ShareDownload[]    findAll()
 * @method ShareDownload[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ShareDownloadRepository extends ServiceEntityRepository
{
    public function __construct(Finders $finders)
    {
        parent::__construct($finders, ShareDownload::class);
    }

    /**
     * One more download of a path: the row's counter added to in the database; the first download of a path makes
     * its row (another request making it at the same moment: counted on that one).
     */
    public function increment(string $path): void
    {
        $counted = ['count' => Change::add(1), 'lastAt' => new \DateTimeImmutable()];
        if (0 < $this->updateBy(['path' => $path], $counted)) {
            return;
        }
        try {
            $this->persist((new ShareDownload($path, 1))->setLastAt(new \DateTimeImmutable()), true);
        } catch (UniqueConstraintViolation) {
            $this->updateBy(['path' => $path], $counted);
        }
    }

    /** @return array<string, int> every path's downloads */
    public function counts(): array
    {
        // The values as the database holds them now (increment() counts there, not on loaded entities).
        return array_map('intval', array_column($this->findValuesBy(['path', 'count']), 'count', 'path'));
    }

    /** The counts of a path and of everything under it: gone with it, at once. */
    public function deleteUnder(string $path): void
    {
        $this->deleteBy([SharedPathRepository::under($path)]);
    }

    /** The counts of a path and of everything under it, under its new path, at once. */
    public function moveUnder(string $from, string $to): void
    {
        $this->updateBy([SharedPathRepository::under($from)], ['path' => Change::replacePrefix($from, $to)]);
    }
}
