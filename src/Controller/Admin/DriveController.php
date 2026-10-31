<?php

namespace Base\Drive\Controller\Admin;

use Base\Drive\Storage\DocumentsStorage;
use Base\Drive\Storage\DriveClient;
use Base\Drive\Storage\DriveSettings;
use Base\Drive\Tree\DocumentsTree;
use Base\Drive\Tree\TreeInterface;
use Google\Service\Drive\DriveFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The drive's Google Drive, from the back office: connect the site's Google account, pick (or
 * create) the folder the files live in, lay out the site's folders (TreeInterface, when it has
 * one), disconnect. Until then the files stay on the site's storage, and move nowhere by
 * themselves. Loaded with omnibase/admin and google/apiclient (config/services.php).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/drive')]
class DriveController extends AbstractController
{
    public function __construct(
        private readonly DriveSettings $settings,
        private readonly DriveClient $client,
        private readonly TranslatorInterface $translator,
        private readonly ?TreeInterface $tree = null,
    ) {
    }

    #[Route('', name: 'app_admin_drive')]
    public function index(DocumentsStorage $storage): Response
    {
        $folders = [];
        $error = null;
        if ($this->client->isConfigured() && $this->settings->refreshToken()) {
            try {
                $list = $this->client->drive()->files->listFiles([
                    'q' => "mimeType = 'application/vnd.google-apps.folder' and trashed = false and 'root' in parents",
                    'fields' => 'files(id, name)', 'pageSize' => 50, 'orderBy' => 'name',
                ]);
                $folders = array_map(fn (DriveFile $f) => ['id' => $f->getId(), 'name' => $f->getName()], $list->getFiles());
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('@Drive/admin/drive.html.twig', [
            'configured' => $this->client->isConfigured(),
            'settings' => $this->settings,
            'onDrive' => $storage->isDrive(),
            'folders' => $folders,
            'error' => $error,
            'tree' => $this->tree,
        ]);
    }

    #[Route('/connect', name: 'app_admin_drive_connect')]
    public function connect(Request $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('drive_state', $state);

        return $this->redirect($this->client->authorizationUrl($state));
    }

    #[Route('/callback', name: 'app_admin_drive_callback')]
    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->getSession()->remove('drive_state');
        if (!$expected || !hash_equals($expected, (string) $request->query->get('state')) || !$request->query->get('code')) {
            $this->addFlash('error', $this->trans('drive.admin.flash.refused'));

            return $this->redirectToRoute('app_admin_drive');
        }
        try {
            $token = $this->client->exchange((string) $request->query->get('code'));
            $this->settings->connect($token['refresh_token'], $token['email']);
            $this->addFlash('success', $this->trans('drive.admin.flash.connected', ['account' => $token['email'] ? ' ('.$token['email'].')' : '']));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->trans('drive.admin.flash.error', ['message' => $e->getMessage()]));
        }

        return $this->redirectToRoute('app_admin_drive');
    }

    #[Route('/folder', name: 'app_admin_drive_folder', methods: ['POST'])]
    public function folder(Request $request): RedirectResponse
    {
        $this->assertToken($request);
        try {
            $id = (string) $request->request->get('folder');
            if ('new' === $id) {
                $name = trim((string) $request->request->get('name')) ?: $this->trans('drive.admin.folder_default');
                $folder = $this->client->drive()->files->create(new DriveFile(['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder']), ['fields' => 'id, name']);
                $id = $folder->getId();
            } else {
                $name = $this->client->drive()->files->get($id, ['fields' => 'name'])->getName();
            }
            $this->settings->useFolder($id, $name);
            $this->addFlash('success', $this->trans('drive.admin.flash.folder', ['name' => $name]));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->trans('drive.admin.flash.error', ['message' => $e->getMessage()]));
        }

        return $this->redirectToRoute('app_admin_drive');
    }

    #[Route('/tree', name: 'app_admin_drive_tree', methods: ['POST'])]
    public function tree(Request $request, DocumentsTree $documents): RedirectResponse
    {
        $this->assertToken($request);
        if (null === $this->tree) {
            throw $this->createNotFoundException('This site lays out no folders.');
        }
        $created = $documents->create($this->tree);
        $this->addFlash('success', $this->trans('drive.admin.flash.tree', ['count' => $created, 'summary' => $this->tree->summary()]));

        return $this->redirectToRoute('app_admin_drive');
    }

    #[Route('/disconnect', name: 'app_admin_drive_disconnect', methods: ['POST'])]
    public function disconnect(Request $request): RedirectResponse
    {
        $this->assertToken($request);
        $this->settings->disconnect();
        $this->addFlash('success', $this->trans('drive.admin.flash.disconnected'));

        return $this->redirectToRoute('app_admin_drive');
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('drive', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans('@messages.'.$key, $parameters);
    }
}
