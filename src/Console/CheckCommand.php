<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Console;

use Jeytekdev\ApiSync\Pipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check', description: 'CI gate: fail if committed collections are out of sync with the code (does not write files)')]
final class CheckCommand extends Command
{
    use OptionsTrait;

    protected function configure(): void
    {
        $this->configureCommonOptions($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->resolveConfig($input);
        $result = Pipeline::run($config, $config->formats);

        $stale = [];
        foreach ($result as $format => $files) {
            $formatDir = rtrim($config->out, '/') . '/' . $format;
            foreach ($files as $relativePath => $content) {
                $path = $formatDir . '/' . $relativePath;
                $onDisk = is_file($path) ? file_get_contents($path) : null;
                if ($onDisk !== $content) {
                    $stale[] = $path;
                }
            }
        }

        if ($stale === []) {
            $output->writeln('<info>Collections are up to date.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Collections are out of sync with the code. Run "api-sync generate" and commit the result:</error>');
        foreach ($stale as $path) {
            $output->writeln(' - ' . $path);
        }

        return Command::FAILURE;
    }
}
