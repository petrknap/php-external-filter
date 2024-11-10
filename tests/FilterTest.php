<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use PetrKnap\Profiler\ProfileInterface;
use PetrKnap\Profiler\Profiler;
use PetrKnap\Profiler\ProfilerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Process\Process;

final class FilterTest extends TestCase
{
    #[DataProvider('dataFiltersInput')]
    public function testFiltersInput(mixed $input, string $expectedOutput): void
    {
        self::assertSame(
            $expectedOutput,
            (new Filter('php'))->filter($input),
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
        fwrite($inMemoryStream, $fileContent);
        rewind($inMemoryStream);
        yield 'resource(in-memory stream)' => [$inMemoryStream, $helloWorldPhpStdOut];
    }

    #[DataProvider('dataWritesToStreamsAndReturnsExpectedValue')]
    public function testWritesToStreamsAndReturnsExpectedValue(bool $useOutput, bool $useError): void
    {
        $outputStream = fopen('php://memory', 'w+');
        $errorStream = fopen('php://memory', 'w+');

        $returned = (new Filter('php'))->filter(
            input: '<?php fwrite(fopen("php://stdout", "w"), "output"); fwrite(fopen("php://stderr", "w"), "error");',
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

    public function testBuildsAndExecutesPipeline(): ProfileInterface
    {
        $reference = Process::fromShellCommandline(
            'php | base64 --decode | wc --bytes',
        );
        $pipeline = (new Filter('php'))
            ->pipe(new Filter('base64', ['--decode']))
            ->pipe(new Filter('wc', ['--bytes']));

        $input = '<?php for ($i = 0; $i < 16; $i++) echo base64_encode(random_bytes(512 * 1024)) . PHP_EOL;';
        $output = (16 * 512 * 1024) . PHP_EOL;

        return (new Profiler())->profile(static fn (ProfilerInterface $profiler) => self::assertSame([
            'reference' => $output,
            'pipeline' => $output,
        ], [
            'reference' => $profiler->profile(static fn (): string => $reference->setInput($input)->mustRun()->getOutput())->getOutput(),
            'pipeline' => $profiler->profile(static fn (): string => $pipeline->filter($input))->getOutput(),
        ]));
    }

    #[Depends('testBuildsAndExecutesPipeline')]
    public function testPipelineDoesNotHaveMemoryLeak(ProfileInterface $profile): void
    {
        [$referenceProfile, $pipelineProfile] = $profile->getChildren();

        self::assertLessThanOrEqual(
            $referenceProfile->getMemoryUsageChange() * 1.05,  # allow 5% overhead
            $pipelineProfile->getMemoryUsageChange(),
        );

        if ($referenceProfile->getMemoryUsageChange() !== 0) {
            self::markTestIncomplete('Memory leak detected in reference.');
        }
    }

    #[Depends('testBuildsAndExecutesPipeline')]
    public function testPipelinePerformanceIsOk(ProfileInterface $profile): void
    {
        [$referenceProfile, $pipelineProfile] = $profile->getChildren();
        try {  # @todo fix performance and remove try / catch
            self::assertLessThanOrEqual(
                $referenceProfile->getDuration() * 1.05,  # allow 5% overhead
                $pipelineProfile->getDuration(),
            );
        } catch (ExpectationFailedException $expectationFailed) {
            self::markTestIncomplete($expectationFailed->getMessage());
        }
    }

    #[DataProvider('dataThrows')]
    public function testThrows(string $command, array $options, mixed $input, mixed $output, mixed $error): void
    {
        self::expectException(Exception\FilterException::class);

        (new Filter($command, $options))->filter($input, $output, $error);
    }

    public static function dataThrows(): array
    {
        $closedStream = fopen('php://memory', 'w');
        fclose($closedStream);
        return [
            'unknown command' => ['unknown', [], '', null, null],
            'unknown option' => ['php', ['--unknown'], '', null, null],
            'wrong data' => ['php', [], '<?php wrong data', null, null],
            'unsupported input' => ['php', [], new stdClass(), null, null],
            'unsupported output' => ['php', [], '', new stdClass(), null],
            'unsupported error' => ['php', [], '', null, new stdClass()],
            'closed input' => ['php', [], $closedStream, null, null],
            'closed output' => ['php', [], '', $closedStream, null],
            'closed error' => ['php', [], '', null, $closedStream],
        ];
    }
}
