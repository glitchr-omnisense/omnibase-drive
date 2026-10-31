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
 * A path's downloads in the drive set at a number - the ones counted elsewhere before (an old
 * download page's statistics); --add adds to what it has. A path no longer there may count too:
 * a version gone, its downloads kept in its folder's total.
 */
#[AsCommand(name: 'drive:count', description: "A path's downloads in the drive, from statistics kept elsewhere", aliases: ['app:share:count'])]
final class CountCommand extends Command
{
    public function __construct(private readonly FileShare $share)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'The file (or a version no longer there, in its folder)')
            ->addArgument('count', InputArgument::REQUIRED, 'Its downloads')
            ->addOption('add', null, InputOption::VALUE_NONE, 'Add to the ones it has');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $path = FileShare::normalize((string) $input->getArgument('path'));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $count = (int) $input->getArgument('count');
        if ('' === $path || $count < 0) {
            $io->error('A path, and a number of downloads.');

            return Command::FAILURE;
        }
        if ($input->getOption('add')) {
            $count += $this->share->downloads($path);
        }
        $this->share->setDownloads($path, $count);
        $io->success(sprintf('%s: %d downloads%s.', $path, $count, $this->share->isFile($path) ? '' : ' (kept in its folder\'s total)'));

        return Command::SUCCESS;
    }
}
