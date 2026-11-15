<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;

return Architecture::define()
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY(), Preset::YAGNI())
    ->layer('Domain', 'src/Domain/')
    ->layer('Collector', 'src/Collector/')
    ->layer('Command', 'src/Command/')
    ->layer('Application', 'src/Application.php')
    ->ruleset([
        'Domain'    => [],
        'Collector' => ['Domain'],
        'Command'   => ['+Collector'],
        'Application' => ['+Command'],
    ]);
