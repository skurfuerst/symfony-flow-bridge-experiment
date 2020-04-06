<?php

namespace Skurfuerst\ComposerProxyGenerator\DependencyInjection;

use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Skurfuerst\ComposerProxyGenerator\Annotations\Inject;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

class ExposeNecessaryPublicCompilerPass implements CompilerPassInterface
{

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        // 2) this is called.


        $taggedServices = $container->findTaggedServiceIds('skurfuerst_composerproxygenerator.flow_annotation_aware');


        $container->getDefinition('annotations.reader')->setPublic(true);

        $proxyInj = [];
        foreach ($taggedServices as $serviceId => $config) {
            $serviceDefinition = $container->findDefinition($serviceId);

            $r = $container->getReflectionClass($serviceDefinition->getClass());
            /* @var $pi PropertyInfoExtractorInterface */
            $pi = $container->get('property_info');

            $annotationsReader = $container->get('annotations.reader');
            $rc = $container->getReflectionClass($serviceDefinition->getClass());
            $propertyInjections = [];
            foreach ($rc->getProperties() as $property) {
                if ($annotationsReader->getPropertyAnnotation($property, Inject::class)) {
                    $targetType = $pi->getTypes($serviceDefinition->getClass(), $property->getName())[0]->getClassName();
                    $container->findDefinition($targetType)->setPublic(true);
                    $propertyInjections[$property->getName()] = $targetType;
                }
            }

            $proxyInj[$serviceDefinition->getClass()] = [
                'propertyInjections' => $propertyInjections
            ];
        }

        $container->setParameter('xyaaaaa', $proxyInj);
    }

}
