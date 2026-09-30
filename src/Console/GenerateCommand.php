<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Console;

use Jeytekdev\ApiSync\Pipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'generate', description: 'Scan source code and write/update Postman, Insomnia and Bruno collections')]
final class GenerateCommand extends Command
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

        $written = 0;
        foreach ($result as $format => $files) {
            $formatDir = rtrim($config->out, '/') . '/' . $format;
            foreach ($files as $relativePath => $content) {
                $path = $formatDir . '/' . $relativePath;
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0777, true);
                }
                file_put_contents($path, $content);
                $written++;
            }
            $output->writeln(sprintf('<info>%s</info>: %d file(s) written to %s', $format, count($files), $formatDir));
        }

        if ($written === 0) {
            $output->writeln('<comment>No endpoints found - check --source and your extractors.</comment>');
        }

        return Command::SUCCESS;
    }
}
