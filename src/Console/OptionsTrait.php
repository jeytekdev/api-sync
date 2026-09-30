<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Console;

use Jeytekdev\ApiSync\Config\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait OptionsTrait
{
    private function configureCommonOptions(Command $command): void
    {
        $command
            ->addOption('source', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Source directory to scan (repeatable)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Comma-separated formats: postman,insomnia,bruno')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory for generated collections')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to api-sync.php config file', 'api-sync.php')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Collection name')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Base URL variable value')
            ->addOption('prune', null, InputOption::VALUE_NONE, 'Delete endpoints removed from code instead of moving them to _deprecated');
    }

    private function resolveConfig(InputInterface $input): Config
    {
        $config = Config::fromFile((string) $input->getOption('config'));

        $formats = $input->getOption('format');
        $source = $input->getOption('source');

        return $config->withOverrides(
            source: $source !== [] ? $source : null,
            name: $input->getOption('name'),
            baseUrl: $input->getOption('base-url'),
            out: $input->getOption('out'),
            formats: is_string($formats) && $formats !== '' ? array_map('trim', explode(',', $formats)) : null,
            prune: $input->getOption('prune') ? true : null,
        );
    }
}
