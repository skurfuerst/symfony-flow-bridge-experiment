<?php

namespace Skurfuerst\ComposerProxyGenerator\DependencyInjection;

use Skurfuerst\ComposerProxyGenerator\Api\FlowAnnotationAware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

// see https://symfony.com/doc/current/bundles/extension.html#creating-an-extension-class
class SkurfuerstComposerProxyGeneratorExtension extends Extension {

    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {

        // 1) -> register these.
        // TODO: for AOP we need to register additionally...
        $container->registerForAutoconfiguration(FlowAnnotationAware::class)
            ->addTag('skurfuerst_composerproxygenerator.flow_annotation_aware')
        ;
    }
}
