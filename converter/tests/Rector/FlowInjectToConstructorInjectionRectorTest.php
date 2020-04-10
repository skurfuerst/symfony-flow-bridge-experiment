<?php

namespace App\Tests\Rector;


use App\PhpDocNodeFactory\Flow\Property\InjectPhpDocNodeFactory;
use App\Rector\FlowInjectToConstructorInjectionRector;
use Rector\Core\Testing\PHPUnit\AbstractRectorTestCase;

require_once __DIR__ . '/InjectAnnotation.php';

final class FlowInjectToConstructorInjectionRectorTest extends AbstractRectorTestCase
{
    /**
     * @dataProvider provideData()
     */
    public function test(string $file): void
    {
        $this->doTestFile($file);
    }

    public function provideData(): \Iterator
    {
        return $this->yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    /**
     * @return array<string, mixed[]>
     */
    protected function provideConfig(): string
    {
        return __DIR__ . '/rector.yaml';
    }

}
