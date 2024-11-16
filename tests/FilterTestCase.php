<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

abstract class FilterTestCase extends TestCase
{
    #[DataProvider('dataFiltersInput')]
    public function testFiltersInput(mixed $input, string $expectedOutput): void
    {
        self::assertSame(
            $expectedOutput,
            static::createPhpFilter()->filter($input),
        );
    }

    public static function dataFiltersInput(): iterable
    {
        $helloWorldPhpStdOut = 'Hello, World!';
        $helloWorldPhpFile = __DIR__ . '/Some/hello-world.php';

        $fileContent = file_get_contents($helloWorldPhpFile);
        yield 'string(file content)' => [$fileContent, $helloWorldPhpStdOut];

        $filePointer = fopen($helloWorldPhpFile, 'r');
        yield 'resource(file pointer)' => [$filePointer, $helloWorldPhpStdOut];

        $inMemoryStream = fopen('php://memory', 'w+');
        fputs($inMemoryStream, $fileContent);
        rewind($inMemoryStream);
        yield 'resource(in-memory stream)' => [$inMemoryStream, $helloWorldPhpStdOut];
    }

    #[DataProvider('dataWritesToStreamsAndReturnsExpectedValue')]
    public function testWritesToStreamsAndReturnsExpectedValue(bool $useOutput, bool $useError): void
    {
        $outputStream = fopen('php://memory', 'w+');
        $errorStream = fopen('php://memory', 'w+');

        $returned = static::createPhpFilter()->filter(
            input: '<?php fputs(fopen("php://stdout", "w"), "output"); fputs(fopen("php://stderr", "w"), "error");',
            output: $useOutput ? $outputStream : null,
            error: $useError ? $errorStream : null,
        );
        rewind($outputStream);
        rewind($errorStream);

        self::assertSame([
            'returned' => $useOutput ? null : 'output',
            'output' => $useOutput ? 'output' : '',
            'error' => $useError ? 'error' : '',
        ], [
            'returned' => $returned,
            'output' => stream_get_contents($outputStream),
            'error' => stream_get_contents($errorStream),
        ]);
    }

    public static function dataWritesToStreamsAndReturnsExpectedValue(): array
    {
        return [
            'no stream' => [false, false],
            'output stream' => [true, false],
            'error stream' => [false, true],
            'both streams' => [true, true],
        ];
    }

    #[DataProvider('dataFilterThrows')]
    public function testFilterThrows(mixed $input, mixed $output, mixed $error): void
    {
        self::expectException(Exception\FilterException::class);

        static::createPhpFilter()->filter($input, $output, $error);
    }

    public static function dataFilterThrows(): array
    {
        $closedStream = fopen('php://memory', 'w');
        fclose($closedStream);
        return [
            'internal error' => ['<?php throw new Exception();', null, null],
            'unsupported input' => [new stdClass(), null, null],
            'unsupported output' => ['', new stdClass(), null],
            'unsupported error' => ['', null, new stdClass()],
            'closed input' => [$closedStream, null, null],
            'closed output' => ['', $closedStream, null],
            'closed error' => ['', null, $closedStream],
        ];
    }

    /**
     * @todo return {@see Filter}
     */
    abstract protected static function createPhpFilter(): object;
}
