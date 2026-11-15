<?php

declare(strict_types=1);

namespace ConfigCartographer;

use ConfigCartographer\Command\GraphCommand;
use ConfigCartographer\Command\ScanCommand;
use ConfigCartographer\Command\TraceCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('config-cartographer', '0.1.0');

        $this->addCommand(new ScanCommand());
        $this->addCommand(new TraceCommand());
        $this->addCommand(new GraphCommand());
    }
}
