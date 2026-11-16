<?php

namespace Base\Drive\Repository;

use Base\Drive\Entity\SharedPath;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Database\Repository\Finders;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method SharedPath|null find($id, $lockMode = null, $lockVersion = null)
 * @method SharedPath|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method SharedPath[]    findAll()
 * @method SharedPath[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class SharedPathRepository extends ServiceEntityRepository
{
    public function __construct(Finders $finders)
    {
        parent::__construct($finders, SharedPath::class);
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
        return $this->findBy([self::under($path)]);
    }

    /**
     * `path = :path OR path LIKE ':path/%'` (the path's own % and _ escaped by StartingWith): a path and
     * everything under it, a criterion of the drive's repositories (PersonalTags uses it too).
     */
    public static function under(string $path, string $field = 'path'): Group
    {
        return Group::of(Parser::SEPARATOR_OR,
            Criterion::of($field, $path),
            Criterion::of($field, $path.'/', Parser::OPTION_STARTING_WITH),
        );
    }
}
