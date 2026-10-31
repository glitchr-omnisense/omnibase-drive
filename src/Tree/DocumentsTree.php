<?php

namespace Base\Drive\Tree;

use Base\Drive\Share\FileShare;

/**
 * A site's layout of folders (TreeInterface) created where the files are (its storage or its
 * Google Drive): each folder missing made, who each is for set at the same time. Folders already
 * there are left as they are, and so is what they hold.
 */
class DocumentsTree
{
    public function __construct(private readonly FileShare $share)
    {
    }

    /** @return int the folders created */
    public function create(TreeInterface $tree): int
    {
        $created = 0;
        $folders = $tree->folders();
        foreach (array_keys($folders) as $path) {
            $path = FileShare::normalize((string) $path);
            if (!$this->share->isDirectory($path)) {
                $this->share->createDirectory($path);
                ++$created;
            }
        }
        foreach ($folders as $path => $audience) {
            if (null !== $audience) {
                $this->share->describe(FileShare::normalize((string) $path), $audience, $this->share->row(FileShare::normalize((string) $path))?->getDescription());
            }
        }

        return $created;
    }
}
