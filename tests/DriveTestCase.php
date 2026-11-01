<?php

namespace Tests\Base\Drive;

use Base\Drive\Audience\Audiences;
use Base\Drive\Audience\MembersAudience;
use Base\Drive\Audience\ReadersAudience;
use Base\Drive\Audience\StaffAudience;
use Base\Drive\Audience\UsersAudience;
use Base\Drive\Entity\RetentionEvent;
use Base\Drive\Entity\SharedPath;
use Base\Drive\Repository\RetentionEventRepository;
use Base\Drive\Repository\ShareDownloadRepository;
use Base\Drive\Repository\SharedPathRepository;
use Base\Drive\Share\FileShare;
use Base\Drive\Share\Retention;
use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * A drive on a temporary local storage, what the team said of its paths in memory ($rows), the
 * retention log too ($events): no kernel, no database. What is persisted is kept there.
 */
abstract class DriveTestCase extends TestCase
{
    protected string $directory;
    protected Filesystem $storage;
    /** @var array<string, SharedPath> */
    protected array $rows = [];
    /** @var list<RetentionEvent> */
    protected array $events = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/drive-test-'.bin2hex(random_bytes(4));
        $this->storage = new Filesystem(new LocalFilesystemAdapter($this->directory));
    }

    protected function tearDown(): void
    {
        $this->storage->deleteDirectory('');
        @rmdir($this->directory);
    }

    protected function share(string $defaultAudience = 'staff', ?Retention $retention = null): FileShare
    {
        $paths = $this->createStub(SharedPathRepository::class);
        $paths->method('byPath')->willReturnCallback(fn () => $this->rows);
        $downloads = $this->createStub(ShareDownloadRepository::class);
        $downloads->method('counts')->willReturn([]);

        return new FileShare($this->storage, $paths, $this->entityManager(), $downloads, $defaultAudience, null, $retention);
    }

    /** The retention lock over the log in memory, on unless said otherwise, by $clock's time when given. */
    protected function retention(bool $enabled = true, ?ClockInterface $clock = null): Retention
    {
        $log = $this->createStub(RetentionEventRepository::class);
        $log->method('log')->willReturnCallback(fn () => $this->events);

        return new Retention($log, $this->entityManager(), $enabled, 'ROLE_SUPERADMIN', $clock);
    }

    /** What is persisted is kept in memory: a path's row in $rows, a line of the log in $events. */
    protected function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof SharedPath) {
                $this->rows[$entity->getPath()] = $entity;
            } elseif ($entity instanceof RetentionEvent && !\in_array($entity, $this->events, true)) {
                $this->events[] = $entity;
            }
        });
        $entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof SharedPath) {
                unset($this->rows[$entity->getPath()]);
            }
        });

        return $entityManager;
    }

    protected function row(string $path, string $audience, ?bool $listed = null): SharedPath
    {
        return $this->rows[$path] = (new SharedPath($path, $audience))->setListed($listed);
    }

    /** An account: the core's User, its id and name given, nothing else of it built (no kernel, so none of the App\ classes it refers to). */
    protected function user(int $id, ?string $name = null): User
    {
        return new class($id, $name ?? 'user'.$id) extends User {
            public function __construct(private readonly int $testId, private readonly string $testName)
            {
            }

            public function getId(): ?int
            {
                return $this->testId;
            }

            public function getUserIdentifier(): string
            {
                return $this->testName;
            }

            public function __toString(): string
            {
                return $this->testName;
            }
        };
    }

    protected static function audiences(iterable $more = []): Audiences
    {
        return new Audiences([new StaffAudience(), new ReadersAudience(), new UsersAudience(), new MembersAudience(), ...$more]);
    }
}
