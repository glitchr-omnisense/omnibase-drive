<?php

namespace Base\Drive\Repository;

use Base\Drive\Entity\ShareDownload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ShareDownload> */
class ShareDownloadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShareDownload::class);
    }

    /** One more download of a path: in one statement, whatever else is counting at the same time. */
    public function increment(string $path): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO shareDownload (path, count, lastAt) VALUES (:path, 1, NOW()) ON DUPLICATE KEY UPDATE count = count + 1, lastAt = NOW()',
            ['path' => $path],
        );
    }

    /** @return array<string, int> every path's downloads */
    public function counts(): array
    {
        return array_map('intval', array_column($this->getEntityManager()->getConnection()->fetchAllAssociative('SELECT path, count FROM shareDownload'), 'count', 'path'));
    }

    /** The counts of a path and of everything under it: gone with it. */
    public function deleteUnder(string $path): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM shareDownload WHERE path = :path OR path LIKE :under',
            ['path' => $path, 'under' => addcslashes($path, '%_').'/%'],
        );
    }
}
