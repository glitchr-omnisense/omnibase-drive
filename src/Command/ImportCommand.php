<?php

namespace Base\Drive\Command;

use Base\Drive\Share\FileShare;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A folder of files into the drive (Base\Drive\Share\FileShare), as it is: its tree under `target`
 * (the root by default). Hidden files (.git, .htaccess) stay behind; a file already there with
 * the same size is left. For what is too big or too many to upload from the page.
 */
#[AsCommand(name: 'drive:import', description: 'A folder of files into the drive', aliases: ['app:share:import'])]
final class ImportCommand extends Command
{
    public function __construct(private readonly FileShare $share)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'The folder to import')
            ->addArgument('target', InputArgument::OPTIONAL, 'Where in the drive', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $from = rtrim((string) $input->getArgument('directory'), '/');
        if (!is_dir($from)) {
            $io->error(sprintf('%s is not a folder.', $from));

            return Command::FAILURE;
        }
        try {
            $target = FileShare::normalize((string) $input->getArgument('target'));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $copied = $kept = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), \strlen($from) + 1);
            if (!$file->isFile() || preg_match('#(^|/)\.#', $relative)) {
                continue;
            }
            $path = ltrim($target.'/'.$relative, '/');
            if ($this->share->isFile($path) && $this->share->size($path) === $file->getSize()) {
                ++$kept;
                continue;
            }
            $stream = fopen($file->getPathname(), 'r');
            $this->share->write($path, $stream);
            fclose($stream);
            ++$copied;
        }

        $io->success(sprintf('%d files copied into the drive%s, %d already there.', $copied, '' !== $target ? ' ('.$target.')' : '', $kept));

        return Command::SUCCESS;
    }
}
