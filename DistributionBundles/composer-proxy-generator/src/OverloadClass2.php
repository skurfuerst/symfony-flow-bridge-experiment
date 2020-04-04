<?php

namespace Skurfuerst\ComposerProxyGenerator;

use Composer\Script\Event;
use Composer\IO\IOInterface;
use Neos\Flow\ObjectManagement\Proxy\Exception;

class OverloadClass2
{
    const EXTRA_OVERLOAD_CACHE_DIR = 'composer-overload-cache-dir';
    const EXTRA_OVERLOAD_CACHE_DIR_DEV = 'composer-overload-cache-dir-dev';
    const EXTRA_OVERLOAD_CLASS = 'composer-overload-class';
    const EXTRA_OVERLOAD_CLASS_DEV = 'composer-overload-class-dev';
    const EXTRA_OVERLOAD_DUPLICATE_ORIGINAL_FILE = 'duplicate-original-file';
    const NAMESPACE_PREFIX = 'SkurfuerstProxyOriginals';

    protected static $overrideClassMap = [];

    public static function overload(Event $event)
    {
        self::$overrideClassMap = [];
        $extra = $event->getComposer()->getPackage()->getExtra();

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

                        static::copyAndRenameOriginalClassToCacheDir(
                            'var/cache/SkurfuerstProxy',
                            $className,
                            $file,
                            $event->getIO()
                        );

                        self::$overrideClassMap[$className] = 'var/cache/SkurfuerstProxy/' . str_replace('\\', '_', $className) . '.php';
                    }
                }
            }
        }

        // 2nd pass: check AOP etc...
        // $loader = require 'vendor/autoload.php';
        // see https://stackoverflow.com/questions/48853306/how-to-get-the-file-path-where-a-class-would-be-loaded-from-while-using-a-compos

        /*static::defineAutoloadExcludeFromClassmap($event);
        static::defineAutoloadFiles($event);*/
    }

    public static function post(Event $event)
    {
        rename('vendor/autoload.php', 'vendor/autoload_orig.php');
            $autoload = '<?php' . chr(10)
                . '$loader = require(__DIR__ . \'/autoload_orig.php\');' . chr(10);

            $autoload .= '$extraClassMap = [' . chr(10);
            foreach (self::$overrideClassMap as $className => $filePath) {
                $autoload .= '    ' . var_export($className, true) . ' => __DIR__ . \'/../\' . ' . var_export($filePath, true) . ',' . chr(10);
            }
            $autoload .= '];' . chr(10);

            $autoload .= '$loader->addClassMap($extraClassMap);' . chr(10);
            $autoload .= 'return $loader;';

        file_put_contents('vendor/autoload.php', $autoload);
    }

    protected static function getClassNameDefinedInFile($file): string {
        // taken from https://stackoverflow.com/questions/7153000/get-class-name-from-file
        $fp = fopen($file, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;
        while (!$class) {
            if (feof($fp)) break;

            $buffer .= fread($fp, 512);
            $tokens = token_get_all($buffer);

            if (strpos($buffer, '{') === false) continue;

            for (;$i<count($tokens);$i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j=$i+1;$j<count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= '\\'.$tokens[$j][1];
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j=$i+1;$j<count($tokens);$j++) {
                        if ($tokens[$j] === '{') {
                            $class = $tokens[$i+2][1];
                        }
                    }
                }
            }
        }

        return $namespace . '\\' . $class;
    }

    protected static function defineAutoloadExcludeFromClassmap(Event $event)
    {
        $originalFiles = ($event->isDevMode())
            ? [static::EXTRA_OVERLOAD_CLASS, static::EXTRA_OVERLOAD_CLASS_DEV]
            : [static::EXTRA_OVERLOAD_CLASS];
        $overloadFiles = ($event->isDevMode()) ? [] : [static::EXTRA_OVERLOAD_CLASS_DEV];
        $autoload = static::getAutoload($event);
        $extra = $event->getComposer()->getPackage()->getExtra();

        foreach ($originalFiles as $env) {
            if (array_key_exists($env, $extra)) {
                foreach ($extra[$env] as $className => $infos) {
                    $autoload['exclude-from-classmap'][] = $infos['original-file'];
                }
            }
        }

        foreach ($overloadFiles as $env) {
            if (array_key_exists($env, $extra)) {
                foreach ($extra[$env] as $className => $infos) {
                    $autoload['exclude-from-classmap'][] = $infos['overload-file'];
                }
            }
        }

        $event->getComposer()->getPackage()->setAutoload($autoload);
    }

    protected static function defineAutoloadFiles(Event $event)
    {
        $extra = $event->getComposer()->getPackage()->getExtra();

        if ($event->isDevMode()) {
            $envs = [static::EXTRA_OVERLOAD_CLASS, static::EXTRA_OVERLOAD_CLASS_DEV];
            $cacheDirKey = static::EXTRA_OVERLOAD_CACHE_DIR_DEV;
            if (array_key_exists($cacheDirKey, $extra) === false) {
                $cacheDirKey = static::EXTRA_OVERLOAD_CACHE_DIR;
            }
        } else {
            $envs = [static::EXTRA_OVERLOAD_CLASS];
            $cacheDirKey = static::EXTRA_OVERLOAD_CACHE_DIR;
        }
        if (array_key_exists($cacheDirKey, $extra) === false) {
            throw new \Exception('You must specify extra/' . $cacheDirKey . ' in composer.json');
        }
        $cacheDir = $extra[$cacheDirKey];

        $autoload = static::getAutoload($event);

        foreach ($envs as $extraKey) {
            if (array_key_exists($extraKey, $extra)) {
                foreach ($extra[$extraKey] as $className => $infos) {
                    if (
                        array_key_exists(static::EXTRA_OVERLOAD_DUPLICATE_ORIGINAL_FILE, $infos) === false
                        || $infos[static::EXTRA_OVERLOAD_DUPLICATE_ORIGINAL_FILE] === false
                    ) {
                        static::copyAndRenameOriginalClassToCacheDir(
                            $cacheDir,
                            $className,
                            $infos['original-file'],
                            $event->getIO()
                        );
                    }
                    $autoload['files'][$className] = $infos['overload-file'];

                    $message = '<info>' . $infos['original-file'] . '</info>';
                    $message .= ' is overloaded by <comment>' . $infos['overload-file'] . '</comment>';
                    $event->getIO()->write($message, true, IOInterface::VERBOSE);
                }
            }
        }

        $event->getComposer()->getPackage()->setAutoload($autoload);
    }

    /** @return array */
    protected static function getAutoload(Event $event)
    {
        $return = $event->getComposer()->getPackage()->getAutoload();
        if (array_key_exists('files', $return) === false) {
            $return['files'] = array();
        }
        if (array_key_exists('exclude-from-classmap', $return) === false) {
            $return['exclude-from-classmap'] = array();
        }

        return $return;
    }


    /**
     * @param string $cacheDir
     * @param string $fullyQualifiedClassName
     * @param string $filePath
     * @return string
     */
    protected static function copyAndRenameOriginalClassToCacheDir($cacheDir, $fullyQualifiedClassName, $filePath, IOInterface $io)
    {
        $classNameParts = explode('\\', $fullyQualifiedClassName);
        $classNameWithoutNamespace = array_pop($classNameParts);

        $proxyClassCode = 'class ' . $classNameWithoutNamespace . ' extends ' . $classNameWithoutNamespace . self::ORIGINAL_CLASSNAME_SUFFIX . ' {' . chr(10);
        $proxyClassCode .= '}' . chr(10);

        $fileContents = static::generateOriginalClassFileAndProxyCode($filePath, $proxyClassCode);

        $targetFile = $cacheDir . '/' . str_replace('\\', '_', $fullyQualifiedClassName) . '.php';
        file_put_contents($targetFile, $fileContents);
    }



    /**
     * @param string $filePath
     * @param string $fullyQualifiedClassName
     * @return string
     */
    protected static function getPhpForDuplicatedFile($filePath, $fullyQualifiedClassName)
    {
        if (is_readable($filePath) === false) {
            throw new \Exception('File "' . $filePath . '" does not exists, or is not readable.');
        }

        $phpLines = file($filePath);
        $namespace = substr($fullyQualifiedClassName, 0, strrpos($fullyQualifiedClassName, '\\'));
        $nextIsNamespace = false;
        $namespaceFound = null;
        $classesFound = [];
        $phpCodeForNamespace = null;
        $namespaceLine = null;
        $uses = [];
        $addUses = [];
        $isGlobalUse = true;
        $lastUseLine = null;
        $tokens = token_get_all(implode(null, $phpLines));
        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                if ($token[0] === T_NAMESPACE) {
                    $nextIsNamespace = true;
                    $namespaceLine = $token[2];
                } elseif ($isGlobalUse && $token[0] === T_CLASS) {
                    $classesFound[] = static::getClassNameFromTokens($tokens, $index + 1);
                    $isGlobalUse = false;
                } elseif ($token[0] === T_EXTENDS) {
                    static::addUse(static::getClassNameFromTokens($tokens, $index + 1), $namespaceFound, $uses, $addUses);
                } elseif ($isGlobalUse && $token[0] === T_USE) {
                    $uses[] = static::getClassNameFromTokens($tokens, $index + 1);
                    $lastUseLine = $token[2];
                }

                if ($nextIsNamespace) {
                    $phpCodeForNamespace .= $token[1];
                    if ($token[0] === T_NS_SEPARATOR || $token[0] === T_STRING) {
                        $namespaceFound .= $token[1];
                    }
                }
            } elseif ($nextIsNamespace && $token === ';') {
                $phpCodeForNamespace .= $token;
                if ($namespaceFound !== $namespace) {
                    $message = 'Expected namespace "' . $namespace . '", found "' . $namespaceFound . '" ';
                    $message .= 'in "' . $filePath . '".';
                    throw new \Exception($message);
                }
                $nextIsNamespace = false;
            }
        }

        static::assertOnlyRightClassFound($classesFound, $fullyQualifiedClassName, $filePath);
        static::replaceNamespace($namespaceFound, $phpCodeForNamespace, $phpLines, $namespaceLine);
        static::addUsesInPhpLines($addUses, $phpLines, ($lastUseLine === null ? $namespaceLine : $lastUseLine));

        return implode(null, $phpLines);
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

    /**
     * Removes the first opening php tag ("<?php") from the given $classCode if there is any
     *
     * @param string $classCode
     * @return string the original class code without opening php tag
     */
    static protected function stripOpeningPhpTag($classCode)
    {
        return preg_replace('/^\s*\\<\\?php(.*\n|.*)/', '$1', $classCode, 1);
    }

    /**
     * @param string $fullyQualifiedClassName
     * @param string $filePath
     */
    protected static function assertOnlyRightClassFound(array $classFound, $fullyQualifiedClassName, $filePath)
    {
        $className = substr($fullyQualifiedClassName, strrpos($fullyQualifiedClassName, '\\') + 1);
        if (count($classFound) !== 1) {
            throw new \Exception('Expected 1 class, found "' . implode(', ', $classFound) . '" in "' . $filePath . '".');
        } elseif ($classFound[0] !== $className) {
            $message = 'Expected "' . $className . '" class, found "' . $classFound[0] . '" ';
            $message .= 'in "' . $filePath . '".';
            throw new \Exception($message);
        }
    }

    /**
     * @param string $namespace
     * @param string $phpCodeForNamespace
     * @param array $phpLines
     * @param int $namespaceLine
     */
    protected static function replaceNamespace($namespace, $phpCodeForNamespace, &$phpLines, $namespaceLine)
    {
        $phpLines[$namespaceLine - 1] = str_replace(
            $phpCodeForNamespace,
            'namespace ' . static::NAMESPACE_PREFIX . '\\' . $namespace . ';',
            $phpLines[$namespaceLine - 1]
        );
    }

    /**
     * @param int $index
     * @return string
     */
    protected static function getClassNameFromTokens(array &$tokens, $index)
    {
        $return = null;
        do {
            if (
                is_array($tokens[$index])
                && (
                    $tokens[$index][0] === T_STRING
                    || $tokens[$index][0] === T_NS_SEPARATOR
                )
            ) {
                $return .= $tokens[$index][1];
            }

            $index++;
            $continue =
                is_array($tokens[$index])
                && (
                    $tokens[$index][0] === T_STRING
                    || $tokens[$index][0] === T_NS_SEPARATOR
                    || $tokens[$index][0] === T_WHITESPACE
                );
        } while ($continue);

        if ($return === null) {
            throw new \Exception('Class not found in tokens.');
        }

        return $return;
    }

    /**
     * @param string $className
     * @param string $namespace
     */
    protected static function addUse($className, $namespace, array $uses, array &$addUses)
    {
        if (substr($className, 0, 1) !== '\\') {
            $alreadyInUses = false;
            foreach ($uses as $use) {
                if (substr($use, strrpos($use, '\\') + 1) === $className) {
                    $alreadyInUses = true;
                }
            }

            if ($alreadyInUses === false) {
                $addUses[] = $namespace . '\\' . $className;
            }
        }
    }

    /** @param int $line */
    protected static function addUsesInPhpLines(array $addUses, array &$phpLines, $line)
    {
        $linesBefore = ($line > 0) ? array_slice($phpLines, 0, $line) : [];
        $linesAfter = array_slice($phpLines, $line);

        array_walk($addUses, function(&$addUse) {
            $addUse = 'use ' . $addUse . ';' . "\n";
        });

        $phpLines = array_merge($linesBefore, $addUses, $linesAfter);
    }
}
