<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @todo make it abstract and remove extends
 */
final class Filter extends PipelinableFilter
{
    /**
     * @deprecated use {@see self::new()}
     *
     * @param non-empty-string $command
     * @param array<non-empty-string>|null $options
     */
    public function __construct(
        private readonly string $command,
        private readonly array|null $options = null,
    ) {
    }

    /**
     * @param non-empty-string $command
     * @param array<non-empty-string>|null $options
     */
    public static function new(string $command, array|null $options = null): PipelinableFilter
    {
        return new Filter($command, $options);
    }

    /**
     * @todo move it to {@see ExternalFilter}
     */
    public function filter(mixed $input, mixed $output = null, mixed $error = null): string|null
    {
        self::checkFilterArguments(
            input: $input,
            output: $output,
            error: $error,
        );

        $headlessPipeline = self::transformPipeline($this->buildPipeline());
        $pipelineHead = array_pop($headlessPipeline);
        foreach ($headlessPipeline as $filter) {
            $filter->setInput($input);
            $filter->start(self::buildProcessCallback(
                output: null,
                error: $error,
            ));
            $input = $filter;
        }
        $process = $pipelineHead;

        $process->setInput($input);
        $process->run(self::buildProcessCallback(
            output: $output,
            error: $error,
        ));
        self::checkFinishedProcess($process);

        return $output === null ? $process->getOutput() : null;
    }

    /**
     * @todo move it to {@see ExternalFilter}
     */
    protected function clone(): static
    {
        return new self($this->command, $this->options);
    }

    /**
     * @todo move it to {@see ExternalFilter}
     *
     * @param resource|null $output
     * @param resource|null $error
     */
    private static function buildProcessCallback(mixed $output, mixed $error): callable
    {
        return static function (string $type, string $data) use ($output, $error): void {
            /** @var Process::OUT|Process::ERR $type */
            match ($type) {
                Process::OUT => $output === null or fputs($output, $data),
                Process::ERR => $error === null or fputs($error, $data),
            };
        };
    }

    /**
     * @todo move it to {@see ExternalFilter}
     */
    private static function checkFinishedProcess(Process $finishedProcess): void
    {
        if (!$finishedProcess->isSuccessful()) {
            throw new class ($finishedProcess) extends ProcessFailedException implements Exception\FilterException {
            };
        }
    }

    /**
     * @todo move it to {@see ExternalFilter}
     *
     * @param non-empty-array<PipelinableFilter> $pipeline
     *
     * @return non-empty-array<Process>
     */
    private static function transformPipeline(array $pipeline): array
    {
        $transformedPipeline = [];
        foreach ($pipeline as $filter) {
            if ($filter instanceof self) {
                $transformedPipeline[] = new Process([
                    $filter->command,
                    ...($filter->options ?? []),
                ]);
            } else {
                throw new \BadMethodCallException('$pipeline contains unsupported filter');
            }
        }
        return $transformedPipeline;
    }
}
