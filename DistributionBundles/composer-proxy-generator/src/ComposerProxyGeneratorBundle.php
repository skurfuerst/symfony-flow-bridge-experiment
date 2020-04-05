<?php

namespace Skurfuerst\ComposerProxyGenerator;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ComposerProxyGeneratorBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new DependencyInjection\ExposeNecessaryPublicCompilerPass());
    }
}