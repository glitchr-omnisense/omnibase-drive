<?php

namespace Base\Drive\Tree;

/**
 * A site's own layout of folders, laid out where the files are (its storage or its Google Drive)
 * by DocumentsTree - from the back office's Google Drive page (« Créer les dossiers ») or the
 * site's fixtures. A property company's: its statutes, general meetings, accounts, a folder per
 * home. The page offers it only when the site has one: a service implementing this interface,
 * aliased with #[AsAlias(TreeInterface::class)].
 */
interface TreeInterface
{
    /**
     * @return array<string, string|null> each folder's path (parents before their children; "" is
     *                                    the root) => the key of the audience it is for, or null: as its folder
     */
    public function folders(): array;

    /** What it lays out, in a sentence for the back office. */
    public function summary(): string;
}
