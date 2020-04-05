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
use Neos\Flow\Annotations as Flow;
use Neos\Utility\TypeHandling;

/**
 * Representation of a method within a proxy class
 *
 * @Flow\Proxy(false)
 */
class ProxyMethod
{
    const BEFORE_PARENT_CALL = 1;
    const AFTER_PARENT_CALL = 2;

    /**
     * Fully qualified class name of the original class
     *
     * @var string
     */
    protected $fullOriginalClassName;

    /**
     * Name of the original method
     *
     * @var string
     */
    protected $methodName;

    /**
     * Visibility of the method
     *
     * @var string
     */
    protected $visibility;

    /**
     * @var string
     */
    protected $addedPreParentCallCode = '';

    /**
     * @var string
     */
    protected $addedPostParentCallCode = '';

    /**
     * @var string
     */
    protected $methodParametersCode = '';

    /**
     * @var string
     */
    public $methodBody = '';

    /**
     * @var \ReflectionMethod
     */
    protected $reflectionMethod;
    /**
     * @var DocCommentParser
     */
    private $docCommentParser;
    /**
     * @var AnnotationReader
     */
    private $annotationReader;

    /**
     * Constructor
     *
     * @param string $fullOriginalClassName The fully qualified class name of the original class
     * @param string $methodName Name of the proxy (and original) method
     * @param AnnotationReader $annotationReader
     * @param \ReflectionMethod|null $reflectionMethod
     */
    public function __construct($fullOriginalClassName, $methodName, AnnotationReader $annotationReader, ?\ReflectionMethod $reflectionMethod)
    {
        $this->fullOriginalClassName = $fullOriginalClassName;
        $this->methodName = $methodName;
        $this->annotationReader = $annotationReader;
        $this->reflectionMethod = $reflectionMethod;

        if ($this->reflectionMethod) {
            $this->docCommentParser = new DocCommentParser();
            $this->docCommentParser->parseDocComment($this->reflectionMethod->getDocComment());
        }
    }

    /**
     * Overrides the method's visibility
     *
     * @param string $visibility One of 'public', 'protected', 'private'
     * @return void
     */
    public function overrideMethodVisibility($visibility)
    {
        $this->visibility = $visibility;
    }

    /**
     * Adds PHP code to the body of this method which will be executed before a possible parent call.
     *
     * @param string $code
     * @return void
     */
    public function addPreParentCallCode($code)
    {
        $this->addedPreParentCallCode .= $code;
    }

    /**
     * Adds PHP code to the body of this method which will be executed after a possible parent call.
     *
     * @param string $code
     * @return void
     */
    public function addPostParentCallCode($code)
    {
        $this->addedPostParentCallCode .= $code;
    }

    /**
     * Sets the (exact) code which use used in as the parameters signature for this method.
     *
     * @param string $code Parameters code, for example: '$foo, array $bar, \Foo\Bar\Baz $baz'
     * @return void
     */
    public function setMethodParametersCode($code)
    {
        $this->methodParametersCode = $code;
    }

    protected function getMethodDeclaredReturnType(): ?string{
        if (!$this->reflectionMethod) {
            return null;
        }
        $returnType = $this->reflectionMethod->getReturnType();
        if ($returnType !== null) {
            $returnTypeAsString = (string) $returnType;
        }
        if ($returnTypeAsString !== null && !in_array($returnTypeAsString, ['self', 'null', 'callable', 'void']) && !TypeHandling::isSimpleType($returnTypeAsString)) {
            $returnTypeAsString = '\\' . $returnTypeAsString;
        }
        if ($returnType !== null && $returnType->allowsNull()) {
            $returnTypeAsString = '?' . $returnTypeAsString;
        }

        return $returnTypeAsString;
    }

    /**
     * Renders the PHP code for this Proxy Method
     *
     * @return string PHP code
     */
    public function render()
    {
        $methodDocumentation = $this->buildMethodDocumentation($this->fullOriginalClassName, $this->methodName);
        $methodParametersCode = ($this->methodParametersCode !== '' ? $this->methodParametersCode : $this->buildMethodParametersCode());
        $callParentMethodCode = $this->buildCallParentMethodCode();

        $finalKeyword = $this->reflectionMethod && $this->reflectionMethod->isFinal() ? 'final ' : '';
        $staticKeyword = $this->reflectionMethod && $this->reflectionMethod->isStatic() ? 'static ' : '';

        $visibility = ($this->visibility === null ? $this->getMethodVisibilityString() : $this->visibility);

        $returnType = $this->getMethodDeclaredReturnType();
        $returnTypeIsVoid = $returnType === 'void';
        $returnTypeDeclaration = ($returnType !== null ? ' : ' . $returnType : '');


        $code = '';
        if ($this->addedPreParentCallCode !== '' || $this->addedPostParentCallCode !== '' || $this->methodBody !== '') {
            $code = "\n" .
                $methodDocumentation .
                '    ' . $finalKeyword . $staticKeyword . $visibility . ' function ' . $this->methodName . '(' . $methodParametersCode . ")$returnTypeDeclaration\n    {\n";
            if ($this->methodBody !== '') {
                $code .= "\n" . $this->methodBody . "\n";
            } else {
                $code .= $this->addedPreParentCallCode;
                if ($this->addedPostParentCallCode !== '') {
                    if ($returnTypeIsVoid) {
                        if ($callParentMethodCode !== '') {
                            $code .= '            ' . $callParentMethodCode;
                        }
                    } else {
                        $code .= '            $result = ' . ($callParentMethodCode === '' ? "NULL;\n" : $callParentMethodCode);
                    }
                    $code .= $this->addedPostParentCallCode;
                    if (!$returnTypeIsVoid) {
                        $code .= "        return \$result;\n";
                    }
                } else {
                    if (!$returnTypeIsVoid && $callParentMethodCode !== '') {
                        $code .= '        return ' . $callParentMethodCode . ";\n";
                    }
                }
            }
            $code .= "    }\n";
        }
        return $code;
    }

    /**
     * Tells if enough code was provided (yet) so that this method would actually be rendered
     * if render() is called.
     *
     * @return boolean true if there is any code to render, otherwise false
     */
    public function willBeRendered()
    {
        return ($this->addedPreParentCallCode !== '' || $this->addedPostParentCallCode !== '');
    }

    /**
     * Builds the method documentation block for the specified method keeping the vital annotations
     *
     * @param string $className Name of the class the method is declared in
     * @param string $methodName Name of the method to create the parameters code for
     * @return string $methodDocumentation DocComment for the given method
     */
    protected function buildMethodDocumentation($className, $methodName)
    {
        $methodDocumentation = "    /**\n     * Autogenerated Proxy Method\n";

        if ($this->reflectionMethod !== null) {
            $methodTags = $this->docCommentParser->getTagsValues();
            $allowedTags = ['param', 'return', 'throws'];
            foreach ($methodTags as $tag => $values) {
                if (in_array($tag, $allowedTags)) {
                    if (count($values) === 0) {
                        $methodDocumentation .= '     * @' . $tag . "\n";
                    } else {
                        foreach ($values as $value) {
                            $methodDocumentation  .= '     * @' . $tag . ' ' . $value . "\n";
                        }
                    }
                }
            }
            $methodAnnotations = $this->annotationReader->getMethodAnnotations($this->reflectionMethod);
            foreach ($methodAnnotations as $annotation) {
                $methodDocumentation .= '     * ' . AnnotationRenderer::renderAnnotation($annotation) . "\n";
            }
        }

        $methodDocumentation .= "     */\n";
        return $methodDocumentation;
    }

    /**
     * Builds the PHP code for the parameters of the specified method to be
     * used in a method interceptor in the proxy class
     *
     * @param boolean $addTypeAndDefaultValue If the type and default value for each parameters should be rendered
     * @return string A comma speparated list of parameters
     */
    public function buildMethodParametersCode($addTypeAndDefaultValue = true)
    {
        $methodParametersCode = '';
        $methodParameterTypeName = '';
        $nullableSign = '';
        $defaultValue = '';
        $byReferenceSign = '';

        if ($this->fullOriginalClassName === null || $this->methodName === null) {
            return '';
        }

        if ($this->reflectionMethod) {
            $methodParameters = $this->reflectionMethod->getParameters();
            if (count($methodParameters) > 0) {
                $methodParametersCount = 0;
                foreach ($methodParameters as $methodParameterName => $methodParameterInfo) {
                    if ($addTypeAndDefaultValue) {
                        if ($methodParameterInfo['array'] === true) {
                            $methodParameterTypeName = 'array';
                        } elseif ($methodParameterInfo['scalarDeclaration']) {
                            $methodParameterTypeName = $methodParameterInfo['type'];
                        } elseif ($methodParameterInfo['class'] !== null) {
                            $methodParameterTypeName = '\\' . $methodParameterInfo['class'];
                        } else {
                            $methodParameterTypeName = '';
                        }
                        if (\PHP_MAJOR_VERSION >= 7 && \PHP_MINOR_VERSION >= 1) {
                            $nullableSign = $methodParameterInfo['allowsNull'] ? '?' : '';
                        }
                        if ($methodParameterInfo['optional'] === true) {
                            $rawDefaultValue = $methodParameterInfo['defaultValue'] ?? null;
                            if ($rawDefaultValue === null) {
                                $defaultValue = ' = NULL';
                            } elseif (is_bool($rawDefaultValue)) {
                                $defaultValue = ($rawDefaultValue ? ' = true' : ' = false');
                            } elseif (is_numeric($rawDefaultValue)) {
                                $defaultValue = ' = ' . $rawDefaultValue;
                            } elseif (is_string($rawDefaultValue)) {
                                $defaultValue = " = '" . $rawDefaultValue . "'";
                            } elseif (is_array($rawDefaultValue)) {
                                $defaultValue = ' = ' . $this->buildArraySetupCode($rawDefaultValue);
                            }
                        }
                        $byReferenceSign = ($methodParameterInfo['byReference'] ? '&' : '');
                    }

                    $methodParametersCode .= ($methodParametersCount > 0 ? ', ' : '')
                        . ($methodParameterTypeName ? $nullableSign . $methodParameterTypeName . ' ' : '')
                        . $byReferenceSign
                        . '$'
                        . $methodParameterName
                        . $defaultValue
                    ;
                    $methodParametersCount++;
                }
            }
        }

        return $methodParametersCode;
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
        return 'parent::' . $this->methodName . '(' . $this->buildMethodParametersCode( false) . ");\n";
    }

    /**
     * Builds a string containing PHP code to build the array given as input.
     *
     * @param array $array
     * @return string e.g. 'array()' or 'array(1 => 'bar')
     */
    protected function buildArraySetupCode(array $array)
    {
        $code = 'array(';
        foreach ($array as $key => $value) {
            $code .= (is_string($key)) ? "'" . $key . "'" : $key;
            $code .= ' => ';
            if ($value === null) {
                $code .= 'NULL';
            } elseif (is_bool($value)) {
                $code .= ($value ? 'true' : 'false');
            } elseif (is_numeric($value)) {
                $code .= $value;
            } elseif (is_string($value)) {
                $code .= "'" . $value . "'";
            }
            $code .= ', ';
        }
        return rtrim($code, ', ') . ')';
    }

    /**
     * Returns the method's visibility string found by the reflection service
     * Note: If the reflection service has no information about this method,
     * 'public' is returned.
     *
     * @return string One of 'public', 'protected' or 'private'
     */
    protected function getMethodVisibilityString()
    {
        if ($this->reflectionService->isMethodProtected($this->fullOriginalClassName, $this->methodName)) {
            return 'protected';
        } elseif ($this->reflectionService->isMethodPrivate($this->fullOriginalClassName, $this->methodName)) {
            return 'private';
        }
        return 'public';
    }

    /**
     * Override the method body
     *
     * @param string $methodBody
     * @return void
     */
    public function setMethodBody($methodBody)
    {
        $this->methodBody = $methodBody;
    }
}
