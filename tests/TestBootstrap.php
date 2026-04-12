<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$loader = (new TestBootstrapper())
    ->addCallingPlugin()
    ->addActivePlugins('CwsDevelopmentTools')
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('CwsDevelopmentTools\\Tests\\', __DIR__);
