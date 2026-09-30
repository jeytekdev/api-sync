<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Console;

use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('api-sync', '1.0.0');

        $this->addCommands([
            new GenerateCommand(),
            new CheckCommand(),
        ]);
    }
}
