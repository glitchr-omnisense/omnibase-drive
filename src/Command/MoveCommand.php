<?php

namespace Base\Drive\Command;

use Base\Drive\Share\FileShare;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Files and folders of the drive moved into another folder (a workspace, say): what was
 * said of them and their downloads follow. --tags drops their own tags (their new folder's are
 * theirs now).
 */
#[AsCommand(name: 'drive:move', description: 'Files and folders of the drive into another folder', aliases: ['app:share:move'])]
final class MoveCommand extends Command
{
    public function __construct(private readonly FileShare $share)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('destination', InputArgument::REQUIRED, 'The folder they go into')
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'What moves')
            ->addOption('drop-tags', null, InputOption::VALUE_NONE, 'Their own tags go: they have their new folder\'s');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $moved = 0;
        try {
            $destination = FileShare::normalize((string) $input->getArgument('destination'));
            foreach ((array) $input->getArgument('paths') as $path) {
                $from = FileShare::normalize((string) $path);
                $to = $this->share->move($from, $destination);
                if ($input->getOption('drop-tags') && ($row = $this->share->row($to))) {
                    $this->share->describe($to, $row->getAudience(), $row->getDescription(), []);
                }
                $io->writeln(sprintf('%s -> %s', $from, $to));
                ++$moved;
            }
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $io->success(sprintf('%d moved into %s.', $moved, '' !== $destination ? $destination : 'the root'));

        return Command::SUCCESS;
    }
}
