<?php

namespace Base\Drive\Controller;

use Base\Attributes\Attribute\Route as LocalizedRoute;
use Base\Drive\Audience\Audiences;
use Base\Drive\Entity\ShareTask;
use Base\Drive\People\PeopleInterface;
use Base\Drive\Security\Voter\ShareVoter;
use Base\Drive\Share\FileShare;
use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The drive's page (Base\Drive\Share\FileShare): files shared with the readers, the users, the
 * people of a workspace and whoever the site's audiences let in, as an index of files - a folder
 * lists what it holds (its README under it), a file opens, shows (?preview) or downloads, a folder
 * downloads as a zip (?zip) - never the drive as a whole; a search through every folder (?q);
 * someone the root is not for has a root of their own. Who sees what is ShareVoter's; the staff
 * manage it on the page itself: upload (a dropped folder kept whole), a new folder, who a folder
 * is for, whom it is shared with, a few words on it, move, rename, delete.
 *
 * Its path is the site's: the key app.share of its config/routes_paths.yaml (fr: /cloud/{path}),
 * its route names app_share and app_share_manage. Its page is @Drive/share/index.html.twig, which a
 * site overrides with its own (templates/bundles/DriveBundle/share/index.html.twig).
 */
class ShareController extends AbstractController
{
    /** Opened in the browser rather than downloaded: what it shows by itself. */
    private const INLINE = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'txt', 'md', 'mp3', 'mp4', 'webm', 'ogg'];

    /** What a workspace's people do there besides the team: its tasks, and files they bring (a partner's invoices). */
    private const WORK_ACTIONS = ['task_add', 'task_toggle', 'task_delete', 'upload'];
    /** A search: at least this many letters. */
    private const SEARCH_MIN = 2;

    public function __construct(
        private readonly FileShare $share,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly PeopleInterface $people,
        private readonly Audiences $audiences,
        #[Autowire('%drive.user_class%')] private readonly string $userClass = 'App\Entity\User',
        #[Autowire('%drive.zip_name%')] private readonly string $zipName = 'drive',
        #[Autowire('%drive.listed_root%')] private readonly bool $listedRoot = false,
    ) {
    }

    #[LocalizedRoute('app.share', name: 'app_share', methods: ['GET'], requirements: ['path' => '.*'], defaults: ['path' => ''])]
    public function show(Request $request, string $path = ''): Response
    {
        $path = $this->normalize($path);
        if (!$this->share->isDirectory($path) && !$this->share->isFile($path)) {
            throw $this->createNotFoundException(sprintf('Nothing at "%s" in the drive.', $path));
        }
        // The root, for someone it is not for: their own - the highest folders they see.
        $spaces = '' === $path && null !== $this->getUser() && !$this->isGranted(ShareVoter::VIEW, '');
        if (!$spaces) {
            $this->allowed($path);
        }

        if ($this->share->isFile($path)) {
            // A download counted once: not again for each piece of it a player or a resumed transfer asks for, nor for a preview.
            if (!$request->headers->has('Range') && !$request->query->has('preview')) {
                $this->share->countDownload($path);
            }

            return $this->serve($path, $request);
        }
        if ($request->query->has('zip')) {
            // A folder as one zip - never the drive as a whole.
            if ('' === $path) {
                return $this->redirectToRoute('app_share');
            }
            if ($this->share->weight($path) > FileShare::ZIP_MAX) {
                $this->addFlash('warning', $this->trans('share.flash.too_large'));

                return $this->redirectToRoute('app_share', ['path' => $path]);
            }

            $this->share->countDownload($path);

            return $this->zip($path);
        }

        $visible = fn (string $at) => $this->isGranted(ShareVoter::VIEW, $at);
        // A search goes through every folder, and finds only what its reader sees.
        $query = trim((string) $request->query->get('q', ''));
        $search = mb_strlen($query) >= self::SEARCH_MIN ? $query : null;
        $entries = match (true) {
            null !== $search => $this->share->search($search, $visible),
            $spaces => $this->share->tops($visible),
            default => array_values(array_filter($this->share->entries($path), fn (array $entry) => $visible($entry['path']))),
        };
        $browse = null === $search && !$spaces;
        // A drive whose root lists only what the team listed (drive.listed_root): on its root, and in a
        // search, the rest opens by its link only - but for the workspaces one is of. The team sees it all.
        if ($this->listedRoot && ('' === $path || null !== $search) && !$this->isGranted(ShareVoter::MANAGE)) {
            $entries = array_values(array_filter($entries, fn (array $entry) => $entry['listed'] || $this->isGranted(ShareVoter::WORK, $entry['path'])));
        }
        // Where a result is, said through the folders its reader sees only: not the ones above their space.
        foreach ($entries as &$found) {
            $at = '';
            $found['where'] = [];
            foreach (null !== $search && '' !== $found['in'] ? explode('/', $found['in']) : [] as $part) {
                $at = ltrim($at.'/'.$part, '/');
                if ($visible($at)) {
                    $found['where'][] = $part;
                }
            }
        }
        unset($found);
        // Each entry's audience, the audience itself (its key, label, icon).
        foreach ($entries as &$shown) {
            $shown['audience'] = null !== $shown['audience'] ? $this->audiences->get($shown['audience']) : null;
        }
        unset($shown);
        // The tags to filter this folder by: its entries' - not the folder's own, which they all inherit.
        $filters = [];
        foreach ($entries as &$listed) {
            $listed['tag_keys'] = array_map(FileShare::tagKey(...), $listed['tags']);
        }
        unset($listed);
        foreach ($entries as $entry) {
            foreach ($entry['tags'] as $tag) {
                $filters[FileShare::tagKey($tag)] ??= ['label' => $tag, 'count' => 0];
                ++$filters[FileShare::tagKey($tag)]['count'];
            }
        }
        $inherited = array_map(FileShare::tagKey(...), $this->share->tags($path));
        $filters = array_diff_key($filters, array_flip($inherited));
        uasort($filters, fn (array $a, array $b) => strnatcasecmp($a['label'], $b['label']));
        $crumbs = [];
        $at = '';
        foreach ('' === $path ? [] : explode('/', $path) as $part) {
            $at = ltrim($at.'/'.$part, '/');
            $crumbs[] = ['name' => $part, 'path' => $at];
        }
        // Nothing above what someone sees: no crumb of a folder not for them, a parent not for them is their root.
        $crumbs = array_values(array_filter($crumbs, fn (array $crumb) => $visible($crumb['path'])));
        $parent = '' === $path || null !== $search ? null : (str_contains($path, '/') ? substr($path, 0, strrpos($path, '/')) : '');
        if (null !== $parent && !$visible($parent)) {
            $parent = '';
        }

        $response = $this->render('@Drive/share/index.html.twig', [
            'path' => $path,
            'crumbs' => $crumbs,
            'parent' => $parent,
            'entries' => $entries,
            'search' => $search,
            'query' => $query,
            'spaces' => $spaces,
            'browse' => $browse,
            'row' => $this->share->row($path),
            'audience' => $this->audiences->get($this->share->audience($path)),
            'audiences' => array_values($this->audiences->all()),
            'readme' => $browse ? $this->share->readme($path) : null,
            'listed' => $this->share->listed($path),
            'filters' => $browse ? $filters : [],
            'tag' => (string) $request->query->get('tag', ''),
            'folder_tags' => $this->share->tags($path),
            // The whole drive's total: the team's to see.
            'downloads' => '' === $path && !$this->isGranted(ShareVoter::MANAGE) ? 0 : $this->share->downloads($path),
            'manage' => $this->isGranted(ShareVoter::MANAGE),
            'zippable' => $browse && '' !== $path && [] !== $entries && $this->share->weight($path) <= FileShare::ZIP_MAX,
            // Its workspace - itself or the folder it is in -, its people and its tasks.
            'workspace' => $workspace = $browse ? $this->share->workspace($path) : null,
            'work' => $workspace && $this->isGranted(ShareVoter::WORK, $path),
            'directories' => $this->isGranted(ShareVoter::MANAGE) ? $this->share->directories() : [],
            // Whom it can be shared with, by name and what they are to the site.
            'people' => $browse && $this->isGranted(ShareVoter::MANAGE) ? $this->people->grouped() : [],
            // A workspace's people by their names, not their identifiers.
            'names' => $workspace ? $this->people->names() : [],
        ]);
        // Not listed: not for search engines either.
        if ('' !== $path && !$this->share->listed($path)) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    /** The staff's hand on a folder: upload, a new folder, who it is for and its words, delete. */
    #[LocalizedRoute('app.share', name: 'app_share_manage', methods: ['POST'], requirements: ['path' => '.*'], defaults: ['path' => ''])]
    public function manage(Request $request, string $path = ''): Response
    {
        if (!$this->isCsrfTokenValid('share', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $path = $this->normalize($path);
        $action = (string) $request->request->get('_action');
        // The tasks: the team and the workspace's people; everything else: the team.
        $this->denyAccessUnlessGranted(\in_array($action, self::WORK_ACTIONS, true) ? ShareVoter::WORK : ShareVoter::MANAGE, $path);
        // Its people bring files only where the team let them; elsewhere they read.
        if ('upload' === $action && !$this->isGranted(ShareVoter::MANAGE) && !$this->share->workspace($path)?->allowsDeposit()) {
            throw $this->createAccessDeniedException('Read only.');
        }
        if (!$this->share->isDirectory($path)) {
            throw $this->createNotFoundException();
        }
        $back = $path;

        switch ($action) {
            case 'upload':
                $sent = (array) $request->files->all('files');
                $files = array_filter($sent, fn ($file) => $file instanceof UploadedFile && $file->isValid());
                // A folder dropped from a computer comes file by file, each with its folder in it ("relative"):
                // the folders are made here, under this one, each name as a name of the drive.
                $into = $path;
                foreach (array_filter(array_map(FileShare::name(...), explode('/', str_replace('\\', '/', (string) $request->request->get('relative', ''))))) as $folder) {
                    $into = ltrim($into.'/'.$folder, '/');
                }
                if ($files && !$this->share->isDirectory($into)) {
                    $this->share->createDirectory($into);
                }
                foreach ($files as $file) {
                    $this->share->upload($into, $file);
                }
                // Too large for the server (or none arrived): said so - to the page dropping them, by its status.
                if (\count($files) < \count($sent) || ([] === $sent && $request->isXmlHttpRequest())) {
                    $this->addFlash('warning', $this->trans('share.drop_failed'));
                    if ($request->isXmlHttpRequest()) {
                        return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
                    }
                }
                if ($files) {
                    $this->addFlash('success', $this->trans('share.flash.uploaded', ['count' => \count($files)]));
                }
                break;
            case 'mkdir':
                $name = FileShare::name((string) $request->request->get('name'));
                if ('' !== $name) {
                    $this->share->createDirectory(ltrim($path.'/'.$name, '/'));
                    $back = ltrim($path.'/'.$name, '/');
                }
                break;
            case 'describe':
                $audience = $this->audiences->find((string) $request->request->get('audience'))?->key();
                // Listed or not: as posted (1, 0, or empty for its folder's), kept when the form does not say.
                $listed = $request->request->has('listed') ? match ((string) $request->request->get('listed')) { '1' => true, '0' => false, default => null } : 'keep';
                $this->share->describe($path, $audience, (string) $request->request->get('description'), explode(',', (string) $request->request->get('tags')), $listed);
                $this->addFlash('success', $this->trans('share.flash.described'));
                break;
            case 'delete':
                $target = $this->normalize((string) $request->request->get('target'));
                if ('' !== $target && (str_starts_with($target, ltrim($path.'/', '/')) || '' === $path)) {
                    $this->share->delete($target);
                    $this->addFlash('success', $this->trans('share.flash.deleted', ['name' => basename($target)]));
                }
                break;
            case 'move':
            case 'rename':
                try {
                    $target = $this->normalize((string) $request->request->get('target'));
                    $moved = 'rename' === $action
                        ? $this->share->move($target, \dirname($target) === '.' ? '' : \dirname($target), (string) $request->request->get('name'))
                        : $this->share->move($target, $this->normalize((string) $request->request->get('destination')));
                    $this->addFlash('success', $this->trans('share.flash.moved', ['name' => basename($moved)]));
                } catch (\InvalidArgumentException) {
                    $this->addFlash('warning', $this->trans('share.flash.not_moved'));
                }
                break;
            case 'workspace_create':
                try {
                    $back = $this->share->createWorkspace((string) $request->request->get('name'), $this->audiences->find((string) $request->request->get('audience'))?->key() ?? 'members', (string) $request->request->get('description'));
                } catch (\InvalidArgumentException) {
                    $this->addFlash('warning', $this->trans('share.flash.not_created'));
                }
                break;
            case 'workspace':
                $this->share->setWorkspace($path, (bool) $request->request->get('workspace'));
                break;
            case 'share':
                // Shared with someone by name: read only, unless its people may bring files.
                $user = $this->users()->find((int) $request->request->get('member'));
                if ($user instanceof User) {
                    $this->share->shareWith($path, $user, $request->request->has('deposit') ? true : null);
                    $this->addFlash('success', $this->trans('share.flash.shared', ['who' => (string) $user]));
                } else {
                    $this->addFlash('warning', $this->trans('share.flash.no_member', ['who' => (string) $request->request->get('member')]));
                }
                break;
            case 'deposit':
                $workspace = $this->share->workspace($path);
                if ($workspace && $workspace->getPath() === $path) {
                    $workspace->setDeposit($request->request->getBoolean('deposit'));
                    $this->entityManager->flush();
                }
                break;
            case 'member_add':
                $who = trim((string) $request->request->get('member'));
                $user = $this->account($who);
                $workspace = $this->share->workspace($path);
                if ($user instanceof User && $workspace) {
                    $workspace->addMember($user);
                    $this->entityManager->flush();
                } else {
                    $this->addFlash('warning', $this->trans('share.flash.no_member', ['who' => $who]));
                }
                break;
            case 'member_remove':
                $workspace = $this->share->workspace($path);
                $user = $this->users()->find((int) $request->request->get('member'));
                if ($workspace && $user instanceof User) {
                    $workspace->removeMember($user);
                    $this->entityManager->flush();
                }
                break;
            case 'task_add':
                $workspace = $this->share->workspace($path);
                $title = trim((string) $request->request->get('title'));
                if ($workspace && '' !== $title) {
                    $user = $this->getUser();
                    $workspace->addTask((new ShareTask($title, $user instanceof User ? $user : null))->setPosition($workspace->getTasks()->count()));
                    $this->entityManager->flush();
                }
                break;
            case 'task_toggle':
            case 'task_delete':
                $workspace = $this->share->workspace($path);
                $task = $workspace?->getTasks()->filter(fn (ShareTask $t) => $t->getId() === (int) $request->request->get('task'))->first();
                if ($task) {
                    'task_toggle' === $action ? $task->toggle() : $workspace->removeTask($task);
                    $this->entityManager->flush();
                }
                break;
        }

        return $this->redirectToRoute('app_share', ['path' => $back]);
    }

    /** @return \Doctrine\Persistence\ObjectRepository<User> the accounts (drive.user_class) */
    private function users(): \Doctrine\Persistence\ObjectRepository
    {
        return $this->entityManager->getRepository($this->userClass);
    }

    /** An account by its identifier or its e-mail. */
    private function account(string $who): ?User
    {
        if ('' === $who) {
            return null;
        }
        $metadata = $this->entityManager->getClassMetadata($this->userClass);
        foreach (['username', 'email'] as $field) {
            if ($metadata->hasField($field) && ($user = $this->users()->findOneBy([$field => $who])) instanceof User) {
                return $user;
            }
        }

        return null;
    }

    private function normalize(string $path): string
    {
        try {
            return FileShare::normalize(rawurldecode($path));
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException();
        }
    }

    /** Not for them: to sign in first when nobody is, else refused. */
    private function allowed(string $path): void
    {
        if (!$this->isGranted(ShareVoter::VIEW, $path)) {
            throw $this->createAccessDeniedException(sprintf('"%s" is not shared with you.', $path));
        }
    }

    private function serve(string $path, Request $request): Response
    {
        $name = basename($path);
        $extension = strtolower(pathinfo($name, \PATHINFO_EXTENSION));
        $type = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';
        // Text shown as text, whatever it holds: never run as a page of the site.
        if (\in_array($extension, ['html', 'htm', 'svg', 'xml', 'xhtml'], true)) {
            $type = 'text/plain';
        }
        $inline = \in_array($extension, self::INLINE, true) && !$request->query->has('download');

        $response = new StreamedResponse(function () use ($path) {
            $stream = $this->share->stream($path);
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', str_starts_with($type, 'text/') ? $type.'; charset=utf-8' : $type);
        $response->headers->set('Content-Length', (string) $this->share->size($path));
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition($inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $name, preg_replace('/[^\x20-\x7e]/', '_', $name)));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setLastModified((new \DateTimeImmutable())->setTimestamp($this->share->modified($path)));
        if (!$this->share->listed($path)) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    private function zip(string $path): Response
    {
        $file = $this->share->zip($path, fn (string $file) => $this->isGranted(ShareVoter::VIEW, $file));
        $response = $this->file($file, ('' === $path ? $this->zipName : basename($path)).'.zip');
        $response->deleteFileAfterSend();

        return $response;
    }

    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans('@messages.'.$key, $parameters);
    }
}
