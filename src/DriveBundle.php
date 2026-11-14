<?php

namespace Base\Drive;

use Omnibridge\Symfony\Omnibase\Bundle\AbstractBaseBundle;
use Base\Traits\SingletonTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The drive application on top of omnibase: a site's own cloud of folders and files (FileShare),
 * who each folder is for (the audiences), whom it is shared with by name, workspaces with their
 * people and tasks, search, previews, zips and download counts - on the site's storage, or its
 * Google Drive once connected from the back office.
 */
class DriveBundle extends AbstractBaseBundle
{
    // Own singleton storage rather than sharing AbstractBaseBundle's (see EstateBundle).
    use SingletonTrait;

    public function __construct()
    {
        parent::__construct();
    }

    /** The bundle root is the package root: ./templates is @Drive, ./src/Entity is mapped. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->setMapping($this->getPath().'/src/Entity', 'Base\Drive\Entity', 'App\Entity\Drive');
        $this->setMapping($this->getPath().'/src/Repository', 'Base\Drive\Repository', 'App\Repository\Drive');
    }
}
