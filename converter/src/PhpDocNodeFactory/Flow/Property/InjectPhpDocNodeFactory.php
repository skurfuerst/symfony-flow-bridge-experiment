<?php


namespace App\PhpDocNodeFactory\Flow\Property;


use App\PhpDocNode\Flow\Property\InjectPhpDocNode;
use Rector\BetterPhpDocParser\AnnotationReader\NodeAnnotationReader;
use Rector\BetterPhpDocParser\PhpDocNode\Gedmo\BlameableTagValueNode;
use Rector\BetterPhpDocParser\PhpDocNodeFactory\AbstractBasicPropertyPhpDocNodeFactory;
use Rector\BetterPhpDocParser\PhpDocNodeFactory\AbstractPhpDocNodeFactory;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use Rector\BetterPhpDocParser\PhpDocParser\AnnotationContentResolver;

class InjectPhpDocNodeFactory extends AbstractBasicPropertyPhpDocNodeFactory
{
    public function getClass(): string
    {
        return 'Neos\Flow\Annotations\Inject';
    }

    public function autowireAbstractPhpDocNodeFactory(
        NodeAnnotationReader $nodeAnnotationReader,
        AnnotationContentResolver $annotationContentResolver
    ): void {
        $this->nodeAnnotationReader = $nodeAnnotationReader;
        $this->annotationContentResolver = $annotationContentResolver;
    }

    /**
     * @return BlameableTagValueNode|null
     */
    public function createFromNodeAndTokens(Node $node, TokenIterator $tokenIterator): ?PhpDocTagValueNode
    {
        return $this->createFromNode($node);
    }

    protected function getTagValueNodeClass(): string
    {
        return InjectPhpDocNode::class;
    }
}
