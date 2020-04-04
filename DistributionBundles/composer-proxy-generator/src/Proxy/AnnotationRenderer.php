<?php
namespace Skurfuerst\ComposerProxyGenerator\Proxy;

class AnnotationRenderer {
    /**
     * Render the source (string) form of an Annotation instance.
     *
     * @param \Doctrine\Common\Annotations\Annotation $annotation
     * @return string
     */
    public static function renderAnnotation($annotation)
    {
        $annotationAsString = '@\\' . get_class($annotation);

        $optionNames = get_class_vars(get_class($annotation));
        $optionsAsStrings = [];
        foreach ($optionNames as $optionName => $optionDefault) {
            $optionValue = $annotation->$optionName;
            $optionValueAsString = '';
            if (is_object($optionValue)) {
                $optionValueAsString = self::renderAnnotation($optionValue);
            } elseif (is_scalar($optionValue) && is_string($optionValue)) {
                $optionValueAsString = '"' . $optionValue . '"';
            } elseif (is_bool($optionValue)) {
                $optionValueAsString = $optionValue ? 'true' : 'false';
            } elseif (is_scalar($optionValue)) {
                $optionValueAsString = $optionValue;
            } elseif (is_array($optionValue)) {
                $optionValueAsString = self::renderOptionArrayValueAsString($optionValue);
            }
            switch ($optionName) {
                case 'value':
                    $optionsAsStrings[] = $optionValueAsString;
                    break;
                default:
                    if ($optionValue === $optionDefault) {
                        break;
                    }
                    $optionsAsStrings[] = $optionName . '=' . $optionValueAsString;
            }
        }
        return $annotationAsString . ($optionsAsStrings !== [] ? '(' . implode(', ', $optionsAsStrings) . ')' : '');
    }

    /**
     * Render an array value as string for an annotation.
     *
     * @param array $optionValue
     * @return string
     */
    protected static function renderOptionArrayValueAsString(array $optionValue)
    {
        $values = [];
        foreach ($optionValue as $k => $v) {
            $value = '';
            if (is_string($k)) {
                $value .= '"' . $k . '"=';
            }
            if (is_object($v)) {
                $value .= self::renderAnnotation($v);
            } elseif (is_array($v)) {
                $value .= self::renderOptionArrayValueAsString($v);
            } elseif (is_scalar($v) && is_string($v)) {
                $value .= '"' . $v . '"';
            } elseif (is_bool($v)) {
                $value .= $v ? 'true' : 'false';
            } elseif (is_scalar($v)) {
                $value .= $v;
            }
            $values[] = $value;
        }
        return '{ ' . implode(', ', $values) . ' }';
    }
}