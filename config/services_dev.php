<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AhmedBhs\DoctrineDoctor\Analyzer\Integrity\QueryBuilderBestPracticesAnalyzer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->remove(QueryBuilderBestPracticesAnalyzer::class);
};

