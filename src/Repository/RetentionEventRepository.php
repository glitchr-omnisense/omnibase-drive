<?php

namespace Base\Drive\Repository;

use Base\Drive\Entity\RetentionEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RetentionEvent> */
class RetentionEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RetentionEvent::class);
    }

    /** @return list<RetentionEvent> the whole log, oldest first: a few lines a year */
    public function log(): array
    {
        return $this->findBy([], ['at' => 'ASC', 'id' => 'ASC']);
    }
}
