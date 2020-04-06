<?php

namespace Skurfuerst\ComposerProxyGenerator;

use App\Kernel;
use Composer\Autoload\ClassLoader;
use Composer\Script\Event;
use Doctrine\Common\Annotations\AnnotationReader;
use Skurfuerst\ComposerProxyGenerator\Proxy\ProxyClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\Filesystem\Filesystem;

class OverloadClass2
{


    public static $overrideClassMap = [];

    public static function pre(Event $event)
    {
        $fs = new Filesystem();
        $fs->remove('var/cache/dev');
    }

    public static function post(Event $event)
    {
        require dirname(__DIR__) . '/../../config/bootstrap.php';
        $kernel = new Kernel($_SERVER['APP_ENV'], (bool)$_SERVER['APP_DEBUG']);

        $kernel->boot();


        $param = $kernel->getContainer()->getParameter('xyaaaaa');

        $overrideClassMap = [];
        $annotationsReader = $kernel->getContainer()->get('annotations.reader');
        $autoloader = self::findAutoloader();
        foreach ($param as $className => $config) {
            $originalFilePath = $autoloader->findFile($className);
            if ($config['propertyInjections']) {
                $proxyClassCode = self::buildProxyClassCode($annotationsReader, $className, $config);
                $overrideClassMap[$className] = self::copyAndRenameOriginalClassToCacheDir(
                    'var/cache/SkurfuerstProxy',
                    $className,
                    $proxyClassCode,
                    $originalFilePath
                );
            }
        }

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

    }


    static protected function buildProxyClassCode(AnnotationReader $annotationReader, $className, $config)
    {
        $proxyClass = new ProxyClass($className, $annotationReader);
        //$proxyClass->addTraits(['\\' . PropertyInjectionTrait::class]);

        foreach ($config['propertyInjections'] as $propertyName => $injectType) {
            $proxyClass->getMethod('Flow_Proxy_injectProperties')->addPreParentCallCode('$this->' . $propertyName . ' = $GLOBALS["kernel"]->getContainer()->get(' . var_export($injectType, true) . ');');
        }

        // TODO: make dynamic!!


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
    protected static function copyAndRenameOriginalClassToCacheDir($cacheDir, $fullyQualifiedClassName, $proxyClassCode, $filePath): string
    {
        $classNameParts = explode('\\', $fullyQualifiedClassName);
        $classNameWithoutNamespace = array_pop($classNameParts);

        $fileContents = static::generateOriginalClassFileAndProxyCode($filePath, $proxyClassCode);

        $targetFile = $cacheDir . '/' . str_replace('\\', '_', $fullyQualifiedClassName) . '.php';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($targetFile, $fileContents);
        return $targetFile;
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


    private static function findAutoloader(): ClassLoader
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            throw new \RuntimeException('TODO');
        }

        foreach ($functions as $function) {
            if (is_array($function) && $function[0] instanceof DebugClassLoader) {
                $nestedClassLoader = $function[0]->getClassLoader();
                if (is_array($nestedClassLoader) && $nestedClassLoader[0] instanceof ClassLoader) {
                    return $nestedClassLoader[0];
                }
            }
            if (is_array($function) && $function[0] instanceof ClassLoader) {
                return $function[0];
            }
        }
        throw new \RuntimeException('TODO');
    }


}
