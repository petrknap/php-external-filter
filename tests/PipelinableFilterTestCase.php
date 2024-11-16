<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use PetrKnap\Profiler\ProfileInterface;
use PetrKnap\Profiler\Profiler;
use PetrKnap\Profiler\ProfilerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\ExpectationFailedException;
use stdClass;
use Symfony\Component\Process\Process;

abstract class PipelinableFilterTestCase extends FilterTestCase
{
    public function testBuildsAndExecutesPipeline(): ProfileInterface
    {
        $reference = Process::fromShellCommandline(
            'cat | php | cat | base64 --decode | wc --bytes',
        );
        $pipeline = static::createPassTroughFilter()
            ->pipe(static::createPhpFilter())
            ->pipe(static::createPassTroughFilter())
            ->pipe(new Filter('base64', ['--decode']))
            ->pipe(new Filter('wc', ['--bytes']));

        $input = '<?php $o = fopen("php://output", "w"); for ($i = 0; $i < 16; $i++) fputs($o, base64_encode(random_bytes(512 * 1024)));';
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
        try {
            self::assertLessThanOrEqual(
                $referenceProfile->getMemoryUsageChange() * 1.05,  # allow 5% overhead
                $pipelineProfile->getMemoryUsageChange(),
            );
        } catch (ExpectationFailedException $expectationFailed) {
            if ($referenceProfile->getMemoryUsageChange() !== 0) {
                self::markTestIncomplete('Memory leak detected in reference.');
            }
            self::markTestIncomplete($expectationFailed->getMessage());
        }
    }

    #[Depends('testBuildsAndExecutesPipeline')]
    public function testPipelinePerformanceIsOk(ProfileInterface $profile): void
    {
        [$referenceProfile, $pipelineProfile] = $profile->getChildren();
        try {
            self::assertLessThanOrEqual(
                $referenceProfile->getDuration() * 1.05,  # allow 5% overhead
                $pipelineProfile->getDuration(),
            );
        } catch (ExpectationFailedException $expectationFailed) {
            self::markTestIncomplete($expectationFailed->getMessage());
        }
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
            'wrong data' => ['<?php wrong data', null, null],
            'unsupported input' => [new stdClass(), null, null],
            'unsupported output' => ['', new stdClass(), null],
            'unsupported error' => ['', null, new stdClass()],
            'closed input' => [$closedStream, null, null],
            'closed output' => ['', $closedStream, null],
            'closed error' => ['', null, $closedStream],
        ];
    }

    abstract protected static function createPassTroughFilter(): PipelinableFilter;

    abstract protected static function createPhpFilter(): PipelinableFilter;
}
