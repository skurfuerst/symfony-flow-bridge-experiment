<?php

namespace Skurfuerst\ComposerProxyGenerator\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ExposeNecessaryPublicCompilerPass implements CompilerPassInterface {

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        // TODO: fix paths - here the path is relative to the public folder (which is ofc. wrong)
        $publicServices = json_decode(file_get_contents('../vendor/public_services.json'), true);

        foreach ($publicServices as $publicService) {
            $container->findDefinition($publicService)->setPublic(true);
        }
    }
}
