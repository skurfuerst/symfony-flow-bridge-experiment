<?php

namespace Skurfuerst\ComposerProxyGenerator;

use Composer\Script\Event;
use Composer\IO\IOInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Neos\Flow\ObjectManagement\DependencyInjection\PropertyInjectionTrait;
use Neos\Flow\ObjectManagement\Proxy\Exception;
use Skurfuerst\ComposerProxyGenerator\Proxy\ProxyClass;
use Symfony\Component\Filesystem\Filesystem;

class OverloadClass2
{
    const EXTRA_OVERLOAD_CACHE_DIR = 'composer-overload-cache-dir';
    const EXTRA_OVERLOAD_CACHE_DIR_DEV = 'composer-overload-cache-dir-dev';
    const EXTRA_OVERLOAD_CLASS = 'composer-overload-class';
    const EXTRA_OVERLOAD_CLASS_DEV = 'composer-overload-class-dev';
    const EXTRA_OVERLOAD_DUPLICATE_ORIGINAL_FILE = 'duplicate-original-file';
    const NAMESPACE_PREFIX = 'SkurfuerstProxyOriginals';



    public static function post(Event $event)
    {
        $annotationReader = new AnnotationReader();
        $overrideClassMap = [];
        $extra = $event->getComposer()->getPackage()->getExtra();

        $loader = require 'vendor/autoload.php';
        $publicServices = [];

        foreach ($extra['skurfuerst-proxy-paths'] as $path) {

            // PASS 1: check
            $rdi = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::KEY_AS_PATHNAME | \RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::SELF_FIRST) as $file => $info) {
                // TODO: needle must be at end
                if (strpos($file, '.php') !== false) {
                    // is PHP file
                    $fileContents = file_get_contents($file);
                    if (strpos($fileContents, 'Neos\Flow\Annotations') !== false) {
                        // using Flow Annotations; thus we need to parse / replace this file.
                        $className = self::getClassNameDefinedInFile($file);
                        $className = trim($className, '\\');
                        var_dump($className);

                        $proxyClassCode = static::buildProxyClassCode($annotationReader, $className, $publicServices);

                        static::copyAndRenameOriginalClassToCacheDir(
                            'var/cache/SkurfuerstProxy',
                            $className,
                            $proxyClassCode,
                            $file,
                            $event->getIO()
                        );

                        $overrideClassMap[$className] = 'var/cache/SkurfuerstProxy/' . str_replace('\\', '_', $className) . '.php';
                    }
                }
            }
        }

        // 2nd pass: check AOP etc...
        // $loader = require 'vendor/autoload.php';
        // see https://stackoverflow.com/questions/48853306/how-to-get-the-file-path-where-a-class-would-be-loaded-from-while-using-a-compos

        /*static::defineAutoloadExcludeFromClassmap($event);
        static::defineAutoloadFiles($event);*/


        // 3nd -> rewrite autoloader!!
        rename('vendor/autoload.php', 'vendor/autoload_orig.php');
        $autoload = '<?php' . chr(10)
            . '$loader = require(__DIR__ . \'/autoload_orig.php\');' . chr(10);

        $autoload .= '$extraClassMap = [' . chr(10);
        foreach ($overrideClassMap as $className => $filePath) {
            $autoload .= '    ' . var_export($className, true) . ' => __DIR__ . \'/../\' . ' . var_export($filePath, true) . ',' . chr(10);
        }
        $autoload .= '];' . chr(10);

        $autoload .= '$loader->addClassMap($extraClassMap);' . chr(10);
        $autoload .= 'return $loader;';

        file_put_contents('vendor/autoload.php', $autoload);
        file_put_contents('vendor/public_services.json', json_encode($publicServices));

    }

    static protected function buildProxyClassCode(AnnotationReader $annotationReader, $className, &$publicServices)
    {
        $proxyClass = new ProxyClass($className, $annotationReader);
        //$proxyClass->addTraits(['\\' . PropertyInjectionTrait::class]);

        // TODO: make dynamic!!
        $proxyClass->getMethod('Flow_Proxy_injectProperties')->addPreParentCallCode('$this->foo = $GLOBALS["kernel"]->getContainer()->get("App\Service\Foo");');
        $publicServices['App\Service\Foo'] = 'App\Service\Foo';

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

    protected static function getClassNameDefinedInFile($file): string
    {
        // taken from https://stackoverflow.com/questions/7153000/get-class-name-from-file
        $fp = fopen($file, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;
        while (!$class) {
            if (feof($fp)) break;

            $buffer .= fread($fp, 512);
            $tokens = token_get_all($buffer);

            if (strpos($buffer, '{') === false) continue;

            for (; $i < count($tokens); $i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= '\\' . $tokens[$j][1];
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j] === '{') {
                            $class = $tokens[$i + 2][1];
                        }
                    }
                }
            }
        }

        return $namespace . '\\' . $class;
    }


    /**
     * @param string $cacheDir
     * @param string $fullyQualifiedClassName
     * @param string $filePath
     * @return string
     */
    protected static function copyAndRenameOriginalClassToCacheDir($cacheDir, $fullyQualifiedClassName, $proxyClassCode, $filePath, IOInterface $io)
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
