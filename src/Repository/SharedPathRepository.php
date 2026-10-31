<?php

namespace Base\Drive\Repository;

use Base\Drive\Entity\SharedPath;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SharedPath> */
class SharedPathRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SharedPath::class);
    }

    /** @return array<string, SharedPath> every path the team said something of, by path */
    public function byPath(): array
    {
        $rows = [];
        foreach ($this->findAll() as $row) {
            $rows[$row->getPath()] = $row;
        }

        return $rows;
    }

    /** The rows of a path and of everything under it: renamed or deleted with it. */
    public function findUnder(string $path): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.path = :path OR p.path LIKE :under')
            ->setParameter('path', $path)
            ->setParameter('under', addcslashes($path, '%_').'/%')
            ->getQuery()->getResult();
    }
}
