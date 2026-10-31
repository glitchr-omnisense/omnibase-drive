<?php

namespace Tests\Base\Drive;

use Base\Drive\Audience\Audiences;
use Base\Drive\Audience\MembersAudience;
use Base\Drive\Audience\ReadersAudience;
use Base\Drive\Audience\StaffAudience;
use Base\Drive\Audience\UsersAudience;
use Base\Drive\Entity\SharedPath;
use Base\Drive\Repository\ShareDownloadRepository;
use Base\Drive\Repository\SharedPathRepository;
use Base\Drive\Share\FileShare;
use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * A drive on a temporary local storage, what the team said of its paths in memory ($rows): no
 * kernel, no database.
 */
abstract class DriveTestCase extends TestCase
{
    protected string $directory;
    protected Filesystem $storage;
    /** @var array<string, SharedPath> */
    protected array $rows = [];

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

    protected function share(string $defaultAudience = 'staff'): FileShare
    {
        $paths = $this->createStub(SharedPathRepository::class);
        $paths->method('byPath')->willReturnCallback(fn () => $this->rows);
        $downloads = $this->createStub(ShareDownloadRepository::class);
        $downloads->method('counts')->willReturn([]);

        return new FileShare($this->storage, $paths, $this->createStub(EntityManagerInterface::class), $downloads, $defaultAudience);
    }

    protected function row(string $path, string $audience, ?bool $listed = null): SharedPath
    {
        return $this->rows[$path] = (new SharedPath($path, $audience))->setListed($listed);
    }

    /** An account: the core's User, its id given, nothing else of it built (no kernel, so none of the App\ classes it refers to). */
    protected function user(int $id): User
    {
        return new class($id) extends User {
            public function __construct(private readonly int $testId)
            {
            }

            public function getId(): ?int
            {
                return $this->testId;
            }
        };
    }

    protected static function audiences(iterable $more = []): Audiences
    {
        return new Audiences([new StaffAudience(), new ReadersAudience(), new UsersAudience(), new MembersAudience(), ...$more]);
    }
}
