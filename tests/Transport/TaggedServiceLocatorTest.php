<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Transport;

use Nette\DI\Container;
use PHPUnit\Framework\TestCase;

final class TaggedServiceLocatorTest extends TestCase
{
    /**
     * @dataProvider dataHas()
     */
    public function testHas(string $id, ?string $defaultServiceName, bool $expectedResult): void
    {
        $container = $this->createStub(Container::class);
        $container->method('findByTag')->willReturn(['Foo' => 'foo']);

        $taggedServiceLocator = new TaggedServiceLocator('tagName', $container, $defaultServiceName);

        $this->assertSame($expectedResult, $taggedServiceLocator->has($id));
    }

    /**
     * @return string[][]
     */
    public function dataHas(): array
    {
        return [
            'service exists' => ['foo', null, true],
            'service does not exist and $defaultServiceName is not set' => ['bar', null, false],
            'service does not exist and $defaultServiceName is set' => ['bar', 'Foo', true],
        ];
    }
}
