<?php

declare(strict_types=1);

namespace App\Rector;

use App\PhpDocNode\Flow\Property\InjectPhpDocNode;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Type\UnionType;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\NodeTypeResolver\Node\AttributeKey;


/**
 * modelled after https://github.com/rectorphp/rector/blob/master/src/Rector/Architecture/DependencyInjection/AnnotatedPropertyInjectToConstructorInjectionRector.php
 *
 * Class FlowInjectToConstructorInjectionRector
 * @package App\Rector
 */
class FlowInjectToConstructorInjectionRector extends AbstractRector
{
    /**
     * @var string
     */
    private const INJECT_ANNOTATION = 'Flow\Inject';

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns non-private properties with `@annotation` to private properties and constructor injection',
            [
                new CodeSample(
                    <<<'PHP'
/**
 * @var SomeService
 * @Flow\Inject
 */
public $someService;
PHP
                    ,
                    <<<'PHP'
/**
 * @var SomeService
 */
private $someService;
public function __construct(SomeService $someService)
{
    $this->someService = $someService;
}
PHP
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Property::class];
    }

    /**
     * @param Property $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->shouldSkipProperty($node)) {
            return null;
        }

        /** @var PhpDocInfo $phpDocInfo */
        $phpDocInfo = $node->getAttribute(AttributeKey::PHP_DOC_INFO);
        $phpDocInfo->removeByType(InjectPhpDocNode::class);

        // set to private
        $this->makeProtected($node);
        $node->flags = Class_::MODIFIER_PROTECTED;

        $this->addPropertyToCollector($node);

        return $node;
    }

    private function shouldSkipProperty(Node $node): bool
    {
        /** @var PhpDocInfo $phpDocInfo */
        $phpDocInfo = $node->getAttribute(AttributeKey::PHP_DOC_INFO);

        if (!$phpDocInfo) {
            return true;
        }
        if (! $phpDocInfo->hasByType(InjectPhpDocNode::class)) {
            return true;
        }

        // it needs @var tag as well, to get the type
        return ! $phpDocInfo->getVarTagValue();
    }

    private function addPropertyToCollector(Property $property): void
    {
        $classNode = $property->getAttribute(AttributeKey::CLASS_NODE);
        if (! $classNode instanceof Class_) {
            return;
        }

        $propertyType = $this->getObjectType($property);

        // use first type
        if ($propertyType instanceof UnionType) {
            $propertyType = $propertyType->getTypes()[0];
        }

        $propertyName = $this->getName($property);

        $this->addPropertyToClass($classNode, $propertyType, $propertyName);
    }
}