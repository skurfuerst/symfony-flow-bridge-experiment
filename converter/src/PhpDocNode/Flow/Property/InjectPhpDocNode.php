<?php


namespace App\PhpDocNode\Flow\Property;


use Rector\BetterPhpDocParser\PhpDocNode\Doctrine\AbstractDoctrineTagValueNode;

class InjectPhpDocNode extends AbstractDoctrineTagValueNode
{
    public function __construct(?string $annotationContent = null)
    {
        $this->resolveOriginalContentSpacingAndOrder($annotationContent);
    }

    public function __toString(): string
    {
        $content = '';

        if ($this->hasOpeningBracket) {
            $content .= '(';
        }

        if ($this->hasClosingBracket) {
            $content .= ')';
        }

        return $content;
    }

    public function getShortName(): string
    {
        return '@Flow\Inject';
    }
}
