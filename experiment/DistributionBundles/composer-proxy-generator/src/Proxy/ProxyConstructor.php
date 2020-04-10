<?php
namespace Skurfuerst\ComposerProxyGenerator\Proxy;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Annotations\AnnotationReader;

/**
 * Representation of a constructor method within a proxy class
 *
 */
class ProxyConstructor extends ProxyMethod
{
    /**
     *
     *
     * @param string $fullOriginalClassName The fully qualified class name of the original class
     * @param AnnotationReader $annotationReader
     * @param \ReflectionMethod|null $reflectionMethod
     */
    public function __construct($fullOriginalClassName, AnnotationReader $annotationReader, ?\ReflectionMethod $reflectionMethod)
    {
        parent::__construct($fullOriginalClassName, '__construct', $annotationReader, $reflectionMethod);
    }

    /**
     * Renders the code for a proxy constructor
     *
     * @return string PHP code
     */
    public function render()
    {
        $methodDocumentation = $this->buildMethodDocumentation($this->fullOriginalClassName, $this->methodName);
        $callParentMethodCode = $this->buildCallParentMethodCode();

        $finalKeyword = $this->reflectionMethod && $this->reflectionMethod->isFinal() ? 'final ' : '';
        $staticKeyword = $this->reflectionMethod && $this->reflectionMethod->isStatic() ? 'static ' : '';

        $code = '';
        if ($this->addedPreParentCallCode !== '' || $this->addedPostParentCallCode !== '') {
            $argumentsCode = ($this->reflectionMethod && count($this->reflectionMethod->getParameters()) > 0) ? '        $arguments = func_get_args();' . "\n" : '';
            $code = "\n" .
                $methodDocumentation .
                '    ' . $finalKeyword . $staticKeyword . "public function __construct()\n    {\n" .
                $argumentsCode .
                $this->addedPreParentCallCode . $callParentMethodCode . $this->addedPostParentCallCode .
                "    }\n";
        }
        return $code;
    }

    /**
     * Builds PHP code which calls the original (ie. parent) method after the added code has been executed.
     *
     * @return string PHP code
     */
    protected function buildCallParentMethodCode()
    {
        if (!$this->reflectionMethod) {
            return '';
        }
        if ($this->reflectionMethod->getNumberOfParameters() > 0) {
            return "        call_user_func_array('parent::" . $this->methodName . "', \$arguments);\n";
        } else {
            return "        parent::" . $this->methodName . "();\n";
        }
    }
}
