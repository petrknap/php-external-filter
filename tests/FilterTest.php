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
    public function testFactoryWorks(array $factoryArguments): void
    {
        $filter = Filter::new(...$factoryArguments);

        self::assertInstanceOf(Filter::class, $filter);
        self::assertSame(self::INPUT, $filter->filter(self::INPUT));
    }

    public static function dataFactoryWorks(): array
    {
        return [
            'command' => [['command' => 'cat']],
            'command with options' => [['command' => 'cat', 'options' => ['--show-nonprinting']]],
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
            'command with extra' => [['command' => 'cat', 'phpFile' => __FILE__]],
            'command with options and extra' => [['command' => 'cat', 'options' => ['--show-nonprinting'], 'phpFile' => __FILE__]],
            'PHP file with extra' => [['phpFile' => __FILE__, 'options' => []]],
            'PHP snippet with extra' => [['phpSnippet' => 'echo "a";', 'options' => []]],
        ];
    }
}
