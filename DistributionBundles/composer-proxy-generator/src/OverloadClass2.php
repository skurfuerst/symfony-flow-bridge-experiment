<?php

namespace Skurfuerst\ComposerProxyGenerator;

use App\Kernel;
use Composer\Script\Event;
use Doctrine\Common\Annotations\AnnotationReader;

class OverloadClass2
{


    public static $overrideClassMap = [];

    public static function post(Event $event)
    {
        $annotationReader = new AnnotationReader();
        self::$overrideClassMap = [];
        $extra = $event->getComposer()->getPackage()->getExtra();

        self::$overrideClassMap = [];


        require dirname(__DIR__).'/../../config/bootstrap.php';
        $kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);

        CompilationMode::withEnabledCompilationMode(function() use($kernel) {
            $kernel->boot();
        });



        // 3nd -> rewrite autoloader!!
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


}
