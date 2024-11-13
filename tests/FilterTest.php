<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use BadMethodCallException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    private const INPUT = b'input';

    #[DataProvider('dataFactoryWorks')]
    public function testFactoryWorks(array $arguments): void
    {
        $filter = Filter::new(...$arguments);

        self::assertInstanceOf(
            Filter::class,
            $filter,
        );

        self::assertSame(self::INPUT, $filter->filter(self::INPUT));
    }

    public static function dataFactoryWorks(): array
    {
        return [
            'command' => [['command' => 'cat']],
            'PHP file' => [['phpFile' => __DIR__ . '/Some/filter.php']],
            'PHP snippet' => [['phpSnippet' => 'fputs(STDOUT, fgets(STDIN));']],
            'PHP snippet (prefixed)' => [['phpSnippet' => '<?php fputs(STDOUT, fgets(STDIN));']],
        ];
    }

    #[DataProvider('dataFactoryThrowsOnBadMethodCall')]
    public function testFactoryThrowsOnBadMethodCall(array $factoryArguments): void
    {
        self::expectException(BadMethodCallException::class);

        Filter::new(...$factoryArguments);
    }

    public static function dataFactoryThrowsOnBadMethodCall(): array
    {
        return [
            'command + extra' => [['command' => 'cat', 'phpFile' => __FILE__]],
            'command + options + extra' => [['command' => 'cat', 'options' => [], 'phpFile' => __FILE__]],
            'phpFile + extra' => [['phpFile' => __FILE__, 'options' => []]],
            'phpSnippet + extra' => [['phpSnippet' => 'echo "a";', 'options' => []]],
        ];
    }
}
