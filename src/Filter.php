<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use BadMethodCallException;
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
     * @note if you are not creating command filter use named arguments
     *
     * @param non-empty-string|null $command
     * @param array<non-empty-string>|null $options
     * @param non-empty-string|null $phpFile path to PHP file which consumes {@see STDIN}
     * @param non-empty-string|null $phpSnippet PHP snippet which consumes {@see STDIN}
     */
    public static function new(
        string|null $command = null,
        array|null $options = null,
        string|null $phpFile = null,
        string|null $phpSnippet = null,
    ): PipelinableFilter {
        $arguments = func_get_args();
        if ($command !== null && self::isNullArray($arguments, 0, 1)) {
            return new Filter($command, $options);
        }

        if ($phpFile !== null && self::isNullArray($arguments, 2)) {
            return new Filter('php', ['-f', $phpFile]);
        }

        if ($phpSnippet !== null && self::isNullArray($arguments, 3)) {
            /** @var non-empty-string $phpSnippet */
            $phpSnippet = (string) preg_replace('/^<\?php\s+/i', '', $phpSnippet);
            return new Filter('php', ['-r', $phpSnippet]);
        }

        throw new BadMethodCallException(__METHOD__ . ' requires valid combination of arguments');
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
        $shellCommandLine = null;
        foreach ($pipeline as $filter) {
            if ($filter instanceof self) {
                $shellCommandLine .= ($shellCommandLine === null ? '' : ' | ') . (new Process([
                    $filter->command,
                    ...($filter->options ?? []),
                ]))->getCommandLine();
            } else {
                throw new BadMethodCallException('$pipeline contains unsupported filter');
            }
        }
        if ($shellCommandLine !== null) {
            $transformedPipeline[] = Process::fromShellCommandline($shellCommandLine);
        }
        return $transformedPipeline;
    }

    /**
     * @param array<int, mixed> $values
     */
    private static function isNullArray(array $values, int ...$exceptIndices): bool
    {
        foreach ($values as $index => $value) {
            if ($value !== null && !in_array($index, $exceptIndices, strict: true)) {
                return false;
            }
        }
        return true;
    }
}
