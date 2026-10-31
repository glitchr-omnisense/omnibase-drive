<?php

namespace Base\Drive\Command;

use Base\Drive\Audience\Audiences;
use Base\Drive\Share\FileShare;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A workspace of the drive from the command line: a new folder at its root - or one already
 * there, made a workspace -, who it is for, a few words on it, its tags.
 */
#[AsCommand(name: 'drive:workspace', description: 'A workspace of the drive: created, or a folder made one', aliases: ['app:share:workspace'])]
final class WorkspaceCommand extends Command
{
    public function __construct(private readonly FileShare $share, private readonly Audiences $audiences)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Its name: a folder of the root')
            ->addOption('audience', null, InputOption::VALUE_REQUIRED, 'Who it is for: an audience\'s key (readers, users, members, staff, or the site\'s own)', 'members')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'A few words shown at its top')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Its tags');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $audience = $this->audiences->find((string) $input->getOption('audience'))?->key();
        if (null === $audience) {
            $io->error('The audience is one of: '.implode(', ', $this->audiences->keys()).'.');

            return Command::FAILURE;
        }
        try {
            $path = FileShare::normalize((string) $input->getArgument('name'));
            if ($this->share->isDirectory($path) && '' !== $path) {
                $this->share->setWorkspace($path, true);
            } else {
                $path = $this->share->createWorkspace($path, $audience);
            }
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $this->share->describe($path, $audience, $input->getOption('description') ?? $this->share->row($path)?->getDescription(), $input->getOption('tag') ?: null);
        $io->success(sprintf('%s is a workspace, for: %s.', $path, $audience));

        return Command::SUCCESS;
    }
}
