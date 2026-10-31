<?php

namespace Base\Drive\Share;

use Base\Drive\Entity\ShareDownload;
use Base\Drive\Entity\SharedPath;
use Base\Drive\Repository\ShareDownloadRepository;
use Base\Drive\Repository\SharedPathRepository;
use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The drive (« le Partage », « Cloud » on a site's pages): files a site shares with its readers,
 * its users, the people of its workspaces and whoever its own audiences let in - an index of
 * files, as a web server's "Index of /", with a little more: who each folder is for, whom it is
 * shared with by name, a few words above it, its README under it, a search through the tree,
 * each person's own root.
 *
 * The files are on a storage of their own (drive.storage, alias documents.storage: the site's
 * Google Drive folder once connected, its flysystem storage drive.local_storage otherwise -
 * Base\Drive\Storage\DocumentsStorage), never public: they are read through ShareController,
 * which asks ShareVoter. What the team says of a path (Base\Drive\Entity\SharedPath: its
 * audience, its description, its tags, whether it is listed) is in the database; a path without
 * a row is as its nearest parent with one - the root as drive.default_audience says; its tags are
 * its own and all its folders'.
 *
 * Paths are relative to the drive's root, "/"-separated, without slashes at their ends
 * ("" is the root). Names beginning with a dot (.git, .htaccess) are never listed nor served.
 */
class FileShare
{
    /** A folder's own words, shown under its list: the first of these found in it. */
    public const READMES = ['README.md', 'README.txt', 'README', 'LISEZMOI.md', 'LISEZMOI.txt', 'LISEZMOI'];
    private const README_MAX = 65536;
    /** A folder larger than this is not offered as one zip: its files, one by one. */
    public const ZIP_MAX = 1024 * 1024 * 1024;

    /** @var array<string, SharedPath>|null */
    private ?array $rows = null;
    /** @var array<string, int>|null */
    private ?array $counts = null;

    public function __construct(
        #[Autowire(service: 'documents.storage')] private readonly FilesystemOperator $storage,
        private readonly SharedPathRepository $paths,
        private readonly EntityManagerInterface $entityManager,
        private readonly ShareDownloadRepository $downloads,
        #[Autowire('%drive.default_audience%')] private readonly string $defaultAudience = 'staff',
        private readonly ?PersonalTags $personal = null,
    ) {
    }

    /**
     * A path as the drive keeps it: no slashes at its ends, no "." or ".." nor hidden part.
     *
     * @throws \InvalidArgumentException when it could lead out of the drive, or names something hidden
     */
    public static function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ('' === $part) {
                continue;
            }
            if (str_starts_with($part, '.') || preg_match('/[\x00-\x1f\x7f]/', $part)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a path of the drive.', $path));
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /** A name fit for the drive, from what was typed or uploaded: its last part, without what a path cannot hold. */
    public static function name(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1f\x7f\/\\\\:*?"<>|]+/', ' ', basename(str_replace('\\', '/', $name))));
        $name = ltrim($name, '. ');

        return mb_substr($name, 0, 180);
    }

    public function isDirectory(string $path): bool
    {
        return '' === $path || $this->storage->directoryExists($path);
    }

    public function isFile(string $path): bool
    {
        return '' !== $path && $this->storage->fileExists($path);
    }

    /**
     * What a folder holds, folders first, each by name.
     *
     * @return list<array{name: string, path: string, directory: bool, size: ?int, modified: ?int, extension: string, audience: ?string, tags: list<string>, own_tags: list<string>, listed: bool}>
     */
    public function entries(string $path): array
    {
        $entries = [];
        /** @var StorageAttributes $item */
        foreach ($this->storage->listContents($path, false) as $item) {
            if (!str_starts_with(basename($item->path()), '.')) {
                $entries[] = $this->entry($item);
            }
        }
        usort($entries, self::order(...));

        return $entries;
    }

    /**
     * What may be found under the root by its name or its tags (accents and case aside), through
     * the whole tree: only what $allowed lets through - what someone sees, and nothing above or
     * beside it. Folders first, then files, each by path; at most $limit.
     *
     * @param callable(string): bool               $allowed
     * @param (callable(string): list<string>)|null $tagsOf the tags it is found by, when not its own and its folders' (the ones its reader sees)
     *
     * @return list<array<string, mixed>> as entries(), each with its folder ("in")
     */
    public function search(string $query, callable $allowed, int $limit = 80, ?callable $tagsOf = null): array
    {
        $words = array_filter(explode(' ', self::fold($query)));
        if (!$words) {
            return [];
        }
        $found = [];
        /** @var StorageAttributes $item */
        foreach ($this->storage->listContents('', true) as $item) {
            if (preg_match('#(^|/)\.#', $item->path())) {
                continue;
            }
            $haystack = self::fold(basename($item->path()).' '.implode(' ', $tagsOf ? $tagsOf($item->path()) : $this->tags($item->path())));
            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    continue 2;
                }
            }
            if ($allowed($item->path())) {
                $found[] = $this->entry($item) + ['in' => str_contains($item->path(), '/') ? substr($item->path(), 0, strrpos($item->path(), '/')) : ''];
            }
        }
        usort($found, fn (array $a, array $b) => [$b['directory'], mb_strtolower($a['path'])] <=> [$a['directory'], mb_strtolower($b['path'])]);

        return \array_slice($found, 0, $limit);
    }

    /**
     * The highest folders someone sees when the root is not for them: each folder $allowed lets
     * through whose own folder it does not - a tenant's, an owner's, a partner's space -, as
     * entries of a root of their own.
     *
     * @param callable(string): bool $allowed
     *
     * @return list<array<string, mixed>>
     */
    public function tops(callable $allowed): array
    {
        $tops = [];
        /** @var StorageAttributes $item */
        foreach ($this->storage->listContents('', true) as $item) {
            $path = $item->path();
            if (!$item->isDir() || preg_match('#(^|/)\.#', $path) || !$allowed($path)) {
                continue;
            }
            $parent = str_contains($path, '/') ? substr($path, 0, strrpos($path, '/')) : '';
            if ('' === $parent || !$allowed($parent)) {
                $tops[] = $this->entry($item);
            }
        }
        usort($tops, self::order(...));

        return $tops;
    }

    /** @return array<string, mixed> a listed item: its name, path, kind, size, date, audience, tags, downloads */
    private function entry(StorageAttributes $item): array
    {
        $rows = $this->rows();
        $name = basename($item->path());

        return [
            'name' => $name,
            'path' => $item->path(),
            'directory' => $item->isDir(),
            'size' => $item->isFile() ? $item->fileSize() : null,
            'modified' => $item->lastModified(),
            'extension' => $item->isFile() ? strtolower(pathinfo($name, \PATHINFO_EXTENSION)) : '',
            // Its own audience's key, when the team set one: shown beside it.
            'audience' => isset($rows[$item->path()]) ? $rows[$item->path()]->getAudience() : null,
            // Its tags to filter by - its own and its folders' -, and its own, shown beside it.
            'tags' => $this->tags($item->path()),
            'own_tags' => isset($rows[$item->path()]) ? $rows[$item->path()]->getTags() : [],
            // Its downloads - a folder's, everything under it and its own zips.
            'downloads' => $this->downloads($item->path()),
            'workspace' => isset($rows[$item->path()]) && $rows[$item->path()]->isWorkspace(),
            // Listed on the root and open to search engines, as itself or its folder says.
            'listed' => $this->listed($item->path()),
        ];
    }

    /** Folders first, each by name. */
    private static function order(array $a, array $b): int
    {
        return [$b['directory'], mb_strtolower($a['name'])] <=> [$a['directory'], mb_strtolower($b['name'])] ?: strnatcasecmp($a['name'], $b['name']);
    }

    /** Lower case, without accents, one space between words: what a search compares. */
    private static function fold(string $text): string
    {
        $text = mb_strtolower(trim(preg_replace('/[\s_\-.]+/u', ' ', $text)));

        return class_exists(\Transliterator::class) ? (string) \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($text) : $text;
    }

    /** @return array{name: string, text: string}|null a folder's README, as text */
    public function readme(string $path): ?array
    {
        foreach (self::READMES as $name) {
            $file = ltrim($path.'/'.$name, '/');
            if ($this->storage->fileExists($file)) {
                $stream = $this->storage->readStream($file);
                $text = (string) stream_get_contents($stream, self::README_MAX);
                fclose($stream);

                return ['name' => $name, 'text' => mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1')];
            }
        }

        return null;
    }

    /** Who a path is for, an audience's key: its own, or its nearest parent's - drive.default_audience when none says. */
    public function audience(string $path): string
    {
        $rows = $this->rows();
        for ($at = $path; ; $at = str_contains($at, '/') ? substr($at, 0, strrpos($at, '/')) : '') {
            if (isset($rows[$at])) {
                return $rows[$at]->getAudience();
            }
            if ('' === $at) {
                return $this->defaultAudience;
            }
        }
    }

    /** @return list<string> a path's tags: its own, then its folders', each once */
    public function tags(string $path): array
    {
        $rows = $this->rows();
        $tags = [];
        for ($at = $path; ; $at = str_contains($at, '/') ? substr($at, 0, strrpos($at, '/')) : '') {
            foreach (isset($rows[$at]) ? $rows[$at]->getTags() : [] as $tag) {
                if (!\in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
                    $tags[] = $tag;
                }
            }
            if ('' === $at) {
                return $tags;
            }
        }
    }

    /** Listed on the root and open to search engines: as itself or its nearest folder says - not listed when none does. */
    public function listed(string $path): bool
    {
        $rows = $this->rows();
        for ($at = $path; ; $at = str_contains($at, '/') ? substr($at, 0, strrpos($at, '/')) : '') {
            if (null !== ($rows[$at] ?? null)?->isListed()) {
                return $rows[$at]->isListed();
            }
            if ('' === $at) {
                return false;
            }
        }
    }

    /** How many times a path was downloaded: a file's, or a folder's - its zips and everything under it. */
    public function downloads(string $path): int
    {
        $this->counts ??= $this->downloads->counts();
        $total = 0;
        foreach ($this->counts as $counted => $count) {
            if ('' === $path || $counted === $path || str_starts_with($counted, $path.'/')) {
                $total += $count;
            }
        }

        return $total;
    }

    /** One more download of a path (a file, or a folder's zip). */
    public function countDownload(string $path): void
    {
        $this->downloads->increment($path);
        $this->counts = null;
    }

    /** A path's downloads set at a number - the old ones of a file restored (drive:count). */
    public function setDownloads(string $path, int $count): void
    {
        $row = $this->downloads->findOneBy(['path' => $path]) ?? new ShareDownload($path);
        $row->setCount($count);
        $this->entityManager->persist($row);
        $this->entityManager->flush();
        $this->counts = null;
    }

    /** A tag as a filter key: "Raspberry Pi" is "raspberry-pi". */
    public static function tagKey(string $tag): string
    {
        return (new \Symfony\Component\String\Slugger\AsciiSlugger())->slug($tag)->lower()->toString();
    }

    /** What the team said of this very path, if anything. */
    public function row(string $path): ?SharedPath
    {
        return $this->rows()[$path] ?? null;
    }

    /**
     * Its audience (a key), its description, its tags and whether it is listed; an audience of
     * null is its parent's again.
     *
     * @param iterable<string>|null $tags   null: the ones it has
     * @param bool|string|null      $listed true/false, null as its folder; 'keep': as it is
     */
    public function describe(string $path, ?string $audience, ?string $description, ?iterable $tags = null, bool|string|null $listed = 'keep'): void
    {
        $row = $this->row($path);
        $description = '' !== trim((string) $description) ? trim((string) $description) : null;
        $tags = null === $tags ? ($row?->getTags() ?? []) : (new SharedPath())->setTags($tags)->getTags();
        $listed = 'keep' === $listed ? $row?->isListed() : $listed;
        if (null === $audience && null === $description && [] === $tags && null === $listed && !$row?->isWorkspace()) {
            if ($row) {
                $this->entityManager->remove($row);
            }
        } else {
            $row ??= new SharedPath($path);
            // Without an audience of its own, the one it had from its parent - written down, since a row holds one.
            $row->setAudience($audience ?? $this->audience(str_contains($path, '/') ? substr($path, 0, strrpos($path, '/')) : ''));
            $row->setDescription($description);
            $row->setTags($tags);
            $row->setListed($listed);
            $this->entityManager->persist($row);
        }
        $this->entityManager->flush();
        $this->rows = null;
    }

    public function createDirectory(string $path): void
    {
        $this->storage->createDirectory($path);
    }

    /** An uploaded file into a folder, under its own name - beside the one already there, if any ("notes (2).pdf"). */
    public function upload(string $directory, UploadedFile $file): string
    {
        $name = self::name($file->getClientOriginalName()) ?: 'fichier';
        $target = ltrim($directory.'/'.$name, '/');
        for ($n = 2; $this->storage->fileExists($target) || $this->storage->directoryExists($target); ++$n) {
            $extension = pathinfo($name, \PATHINFO_EXTENSION);
            $target = ltrim($directory.'/'.pathinfo($name, \PATHINFO_FILENAME).' ('.$n.')'.('' !== $extension ? '.'.$extension : ''), '/');
        }
        $stream = fopen($file->getPathname(), 'r');
        try {
            $this->storage->writeStream($target, $stream);
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        return $target;
    }

    /** The workspace a path is in: itself, or its nearest folder made one - null when none. */
    public function workspace(string $path): ?SharedPath
    {
        $rows = $this->rows();
        for ($at = $path; ; $at = str_contains($at, '/') ? substr($at, 0, strrpos($at, '/')) : '') {
            if (isset($rows[$at]) && $rows[$at]->isWorkspace()) {
                return $rows[$at];
            }
            if ('' === $at) {
                return null;
            }
        }
    }

    /** A new workspace: a folder of the drive's root, who it is for (an audience's key), a few words on it. */
    public function createWorkspace(string $name, string $audience, ?string $description = null): string
    {
        $path = self::name($name);
        if ('' === $path || $this->storage->directoryExists($path) || $this->storage->fileExists($path)) {
            throw new \InvalidArgumentException(sprintf('"%s" cannot be a new workspace.', $name));
        }
        $this->storage->createDirectory($path);
        $row = (new SharedPath($path, $audience))->setDescription($description)->setWorkspace(true);
        $this->entityManager->persist($row);
        $this->entityManager->flush();
        $this->rows = null;

        return $path;
    }

    /**
     * A path shared with someone: they are one of the people of a workspace it is in - its own or
     * any folder above it. They see it whoever else it is for.
     */
    public function isSharedWith(string $path, User $user): bool
    {
        $rows = $this->rows();
        for ($at = $path; ; $at = str_contains($at, '/') ? substr($at, 0, strrpos($at, '/')) : '') {
            if (isset($rows[$at]) && $rows[$at]->isWorkspace() && $rows[$at]->hasMember($user)) {
                return true;
            }
            if ('' === $at) {
                return false;
            }
        }
    }

    /**
     * A folder shared with someone: they see it and what it holds, from their own access, read
     * only unless its people may bring files ($deposit). The folder becomes a workspace (its
     * people, its tasks) if it was not one; whoever else it was for keeps it.
     */
    public function shareWith(string $path, User $user, ?bool $deposit = null): void
    {
        $row = $this->row($path) ?? new SharedPath($path, $this->audience($path));
        $row->setWorkspace(true);
        $row->addMember($user);
        if (null !== $deposit) {
            $row->setDeposit($deposit);
        }
        $this->entityManager->persist($row);
        $this->entityManager->flush();
        $this->rows = null;
    }

    /** A folder made a workspace, or a workspace made a plain folder again (its people and tasks gone). */
    public function setWorkspace(string $path, bool $workspace): void
    {
        $row = $this->row($path) ?? new SharedPath($path, $this->audience($path));
        $row->setWorkspace($workspace);
        if (!$workspace) {
            foreach ($row->getMembers()->toArray() as $member) {
                $row->removeMember($member);
            }
            foreach ($row->getTasks()->toArray() as $task) {
                $row->removeTask($task);
            }
        }
        $this->entityManager->persist($row);
        $this->entityManager->flush();
        $this->rows = null;
    }

    /**
     * A file or a folder moved into another folder (and renamed, when `$name` is given): what it
     * holds, what was said of it and its downloads follow.
     *
     * @return string its new path
     * @throws \InvalidArgumentException into itself, or onto something already there
     */
    public function move(string $from, string $toDirectory, ?string $name = null): string
    {
        $name = null !== $name ? self::name($name) : basename($from);
        $to = ltrim($toDirectory.'/'.$name, '/');
        if ('' === $from || '' === $name || $to === $from) {
            throw new \InvalidArgumentException('Nothing to move.');
        }
        if (str_starts_with($to.'/', $from.'/')) {
            throw new \InvalidArgumentException('A folder does not go into itself.');
        }
        if ($this->storage->fileExists($to) || $this->storage->directoryExists($to) || !$this->isDirectory($toDirectory)) {
            throw new \InvalidArgumentException(sprintf('"%s" is already there, or nowhere to go.', $to));
        }

        if ($this->storage->directoryExists($from)) {
            /** @var StorageAttributes $item */
            foreach (iterator_to_array($this->storage->listContents($from, true)) as $item) {
                $target = $to.substr($item->path(), \strlen($from));
                $item->isDir() ? $this->storage->createDirectory($target) : $this->storage->move($item->path(), $target);
            }
            $this->storage->createDirectory($to);
            $this->storage->deleteDirectory($from);
        } else {
            $this->storage->move($from, $to);
        }

        // What was said of it, under its new path; its downloads too.
        foreach ($this->paths->findUnder($from) as $row) {
            $row->setPath($to.substr($row->getPath(), \strlen($from)));
        }
        $this->entityManager->flush();
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE shareDownload SET path = CONCAT(:to, SUBSTRING(path, :length + 1)) WHERE path = :from OR path LIKE :under',
            ['to' => $to, 'length' => \strlen($from), 'from' => $from, 'under' => addcslashes($from, '%_').'/%'],
        );
        $this->personal?->moved($from, $to);
        $this->rows = null;
        $this->counts = null;

        return $to;
    }

    /** @return list<string> every folder of the drive, to move something into */
    public function directories(): array
    {
        $directories = [''];
        /** @var StorageAttributes $item */
        foreach ($this->storage->listContents('', true) as $item) {
            if ($item->isDir() && !preg_match('#(^|/)\.#', $item->path())) {
                $directories[] = $item->path();
            }
        }
        sort($directories, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $directories;
    }

    /** A file from a stream (drive:import), its folders made on the way; what is there already is replaced. */
    public function write(string $path, $stream): void
    {
        $this->storage->writeStream($path, $stream);
    }

    /** What a folder weighs, every file under it - hidden ones left out. */
    public function weight(string $path): int
    {
        $bytes = 0;
        /** @var StorageAttributes $item */
        foreach ($this->storage->listContents($path, true) as $item) {
            if ($item->isFile() && !preg_match('#(^|/)\.#', $item->path())) {
                $bytes += (int) $item->fileSize();
            }
        }

        return $bytes;
    }

    /** A file, or a folder and all it holds - and what was said of them. */
    public function delete(string $path): void
    {
        if ('' === $path) {
            throw new \InvalidArgumentException('The drive itself is not deleted.');
        }
        if ($this->storage->directoryExists($path)) {
            $this->storage->deleteDirectory($path);
        } else {
            $this->storage->delete($path);
        }
        foreach ($this->paths->findUnder($path) as $row) {
            $this->entityManager->remove($row);
        }
        $this->entityManager->flush();
        $this->downloads->deleteUnder($path);
        $this->personal?->deleted($path);
        $this->rows = null;
        $this->counts = null;
    }

    /** @return resource */
    public function stream(string $path)
    {
        return $this->storage->readStream($path);
    }

    public function size(string $path): int
    {
        return $this->storage->fileSize($path);
    }

    public function modified(string $path): int
    {
        return $this->storage->lastModified($path);
    }

    /**
     * A folder as a zip, in a temporary file the caller deletes: every file under it that
     * `$allowed` lets through (the viewer's rights, folder by folder), hidden ones left out.
     *
     * @param callable(string): bool $allowed
     */
    public function zip(string $path, callable $allowed): string
    {
        $file = tempnam(sys_get_temp_dir(), 'drive-');
        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::OVERWRITE);
        $prefix = '' === $path ? '' : $path.'/';
        // Each file through a temporary copy, not in memory: a folder may hold disk images.
        $copies = [];
        try {
            /** @var StorageAttributes $item */
            foreach ($this->storage->listContents($path, true) as $item) {
                if (!$item->isFile() || preg_match('#(^|/)\.#', $item->path()) || !$allowed($item->path())) {
                    continue;
                }
                $copy = $copies[] = tempnam(sys_get_temp_dir(), 'drive-file-');
                $in = $this->storage->readStream($item->path());
                $out = fopen($copy, 'w');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                $zip->addFile($copy, substr($item->path(), \strlen($prefix)));
                // Already packed (zip, gz, images): stored as they are, quickly.
                if (preg_match('/\.(zip|gz|tgz|gzi|7z|rar|png|jpe?g|gif|webp|mp[34]|webm)$/i', $item->path())) {
                    $zip->setCompressionName(substr($item->path(), \strlen($prefix)), \ZipArchive::CM_STORE);
                }
            }
            // An archive with nothing in it is not written at all: a note instead.
            if (0 === $zip->numFiles) {
                $zip->addFromString('VIDE.txt', "Ce dossier ne contient aucun fichier.\n");
            }
            $zip->close();
        } finally {
            array_map('unlink', array_filter($copies, 'is_file'));
        }

        return $file;
    }

    /** @return array<string, SharedPath> */
    private function rows(): array
    {
        return $this->rows ??= $this->paths->byPath();
    }
}
