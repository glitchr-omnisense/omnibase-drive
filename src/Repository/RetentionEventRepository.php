<?php

namespace Base\Drive\Repository;

use Base\Drive\Entity\RetentionEvent;
use Base\Database\Repository\Finders;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method RetentionEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method RetentionEvent|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method RetentionEvent[]    findAll()
 * @method RetentionEvent[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class RetentionEventRepository extends ServiceEntityRepository
{
    public function __construct(Finders $finders)
    {
        parent::__construct($finders, RetentionEvent::class);
    }

    /** @return list<RetentionEvent> the whole log, oldest first: a few lines a year */
    public function log(): array
    {
        return $this->findBy([], ['at' => 'ASC', 'id' => 'ASC']);
    }
}
