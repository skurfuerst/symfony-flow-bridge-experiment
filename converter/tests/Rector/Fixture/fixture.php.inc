<?php
use Neos\Flow\Annotations as Flow;
class ClassWithInjects
{
    /**
     * @var stdClass
     * @Flow\Inject
     */
    protected $property;

    /**
     * @var DateTimeInterface
     * @Flow\Inject
     */
    protected $otherProperty;
}

?>
-----
<?php
use Neos\Flow\Annotations as Flow;
class ClassWithInjects
{
    /**
     * @var stdClass
     */
    protected $property;

    /**
     * @var DateTimeInterface
     */
    protected $otherProperty;
    public function __construct(\stdClass $property, \DateTimeInterface $otherProperty)
    {
        $this->property = $property;
        $this->otherProperty = $otherProperty;
    }
}

?>