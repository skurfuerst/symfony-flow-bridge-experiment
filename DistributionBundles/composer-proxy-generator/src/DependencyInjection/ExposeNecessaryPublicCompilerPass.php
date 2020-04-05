<?php

namespace Skurfuerst\ComposerProxyGenerator\DependencyInjection;

use Composer\Autoload\ClassLoader;
use Composer\IO\IOInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Skurfuerst\ComposerProxyGenerator\CompilationMode;
use Skurfuerst\ComposerProxyGenerator\OverloadClass2;
use Skurfuerst\ComposerProxyGenerator\Proxy\ProxyClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

class ExposeNecessaryPublicCompilerPass implements CompilerPassInterface
{


    private static function findAutoloader(): ClassLoader
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            throw new \RuntimeException('TODO');
        }

        foreach ($functions as $function) {
            if (is_array($function) && $function[0] instanceof ClassLoader) {
                return $function[0];
            }
        }
        throw new \RuntimeException('TODO');
    }

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        // TODO: the stuff below needs ALWAYS to be done (no matter whether the compiler pass was executed or not)
        $container->findDefinition('App\Service\Foo')->setPublic(true);

        if (!CompilationMode::isEnabled()) {
            return;
        }
        // TODO: fix paths - here the path is relative to the public folder (which is ofc. wrong)
        $taggedServices = $container->findTaggedServiceIds('skurfuerst_composerproxygenerator.flow_annotation_aware');
        var_dump($taggedServices);

        $autoloader = self::findAutoloader();

        $annotationReader = new AnnotationReader();

        foreach ($taggedServices as $serviceId => $config) {
            $serviceDefinition = $container->findDefinition($serviceId);
            $originalFilePath = $autoloader->findFile($serviceDefinition->getClass());

            $proxyClassCode = static::buildProxyClassCode($annotationReader, $serviceDefinition->getClass(), $container);

            static::copyAndRenameOriginalClassToCacheDir(
                'var/cache/SkurfuerstProxy',
                $serviceDefinition->getClass(),
                $proxyClassCode,
                $originalFilePath
            );

            OverloadClass2::$overrideClassMap[$serviceDefinition->getClass()] = 'var/cache/SkurfuerstProxy/' . str_replace('\\', '_', $serviceDefinition->getClass()) . '.php';
        }
        return;
    }

    static protected function buildProxyClassCode(AnnotationReader $annotationReader, $className, ContainerBuilder $container)
    {
        $proxyClass = new ProxyClass($className, $annotationReader);
        //$proxyClass->addTraits(['\\' . PropertyInjectionTrait::class]);

        // TODO: make dynamic!!
        $proxyClass->getMethod('Flow_Proxy_injectProperties')->addPreParentCallCode('$this->foo = $GLOBALS["kernel"]->getContainer()->get("App\Service\Foo");');

        $proxyClass->getMethod('Flow_Proxy_injectProperties')->overrideMethodVisibility('private');

        //$proxyClass->getMethod('number')->addPreParentCallCode(' ');

        $constructorPostCode = '';

        $constructorPostCode .= '        if (\'' . $className . '\' === get_class($this)) {' . "\n";
        $constructorPostCode .= '            $this->Flow_Proxy_injectProperties();' . "\n";
        $constructorPostCode .= '        }' . "\n";

        $constructor = $proxyClass->getConstructor();
        $constructor->addPostParentCallCode($constructorPostCode);

        return $proxyClass->render();
    }


    /**
     * @param string $cacheDir
     * @param string $fullyQualifiedClassName
     * @param string $filePath
     * @return string
     */
    protected static function copyAndRenameOriginalClassToCacheDir($cacheDir, $fullyQualifiedClassName, $proxyClassCode, $filePath)
    {
        $classNameParts = explode('\\', $fullyQualifiedClassName);
        $classNameWithoutNamespace = array_pop($classNameParts);

        $fileContents = static::generateOriginalClassFileAndProxyCode($filePath, $proxyClassCode);

        $targetFile = $cacheDir . '/' . str_replace('\\', '_', $fullyQualifiedClassName) . '.php';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($targetFile, $fileContents);
    }


    const ORIGINAL_CLASSNAME_SUFFIX = '_Original';

    static protected function generateOriginalClassFileAndProxyCode($pathAndFilename, $proxyClassCode)
    {
        $classCode = file_get_contents($pathAndFilename);

        $classNameSuffix = self::ORIGINAL_CLASSNAME_SUFFIX;
        $classCode = preg_replace_callback('/^([a-z\s]*?)(final\s+)?(interface|class)\s+([a-zA-Z0-9_]+)/m', function ($matches) use ($pathAndFilename, $classNameSuffix) {
            return $matches[1] . $matches[3] . ' ' . $matches[4] . $classNameSuffix;
        }, $classCode);

        // comment out "final" keyword, if the method is final and if it is advised (= part of the $proxyClassCode)
        // Note: Method name regex according to http://php.net/manual/en/language.oop5.basic.php
        $classCode = preg_replace_callback('/^(\s*)((public|protected)\s+)?final(\s+(public|protected))?(\s+function\s+)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]+\s*\()/m', function ($matches) use ($pathAndFilename, $classNameSuffix, $proxyClassCode) {
            // the method is not advised => don't remove the final keyword
            if (strpos($proxyClassCode, $matches[0]) === false) {
                return $matches[0];
            }
            return $matches[1] . $matches[2] . '/*final*/' . $matches[4] . $matches[6] . $matches[7];
        }, $classCode);

        $classCode = preg_replace('/\\?>[\n\s\r]*$/', '', $classCode);

        $proxyClassCode .= "\n" . '# PathAndFilename: ' . $pathAndFilename;

        $separator =
            PHP_EOL . '#' .
            PHP_EOL . '# Start of Flow generated Proxy code' .
            PHP_EOL . '#' . PHP_EOL;

        return $classCode . $separator . $proxyClassCode;
    }
}
