<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use InvalidArgumentException;

/**
 * @todo extend {@see Filter}
 *
 * @internal shared logic
 */
abstract class PipelinableFilter
{
    private self|null $previous = null;

    /**
     * @todo move it to {@see Filter}
     *
     * @param string|resource $input
     * @param resource|null $output
     * @param resource|null $error
     *
     * @return ($output is null ? string : null)
     *
     * @throws Exception\FilterException
     */
    abstract public function filter(mixed $input, mixed $output = null, mixed $error = null): string|null;

    public function pipe(self $to): self
    {
        $head = $this;
        foreach ($to->buildPipeline() as $filter) {
            $filter = $filter->clone();
            $filter->previous = $head;
            $head = $filter;
        }

        return $head;
    }

    /**
     * @return non-empty-array<self>
     */
    protected function buildPipeline(): array
    {
        $reversedPipeline = [];
        $filter = $this;
        while ($filter !== null) {
            $reversedPipeline[] = $filter;
            $filter = $filter->previous;
        }
        return array_reverse($reversedPipeline);
    }

    /**
     * @todo move it to {@see Filter}
     */

    protected static function checkFilterArguments(mixed $input, mixed $output, mixed $error): void
    {
        if (!is_string($input) && !is_resource($input)) {
            throw new class ('$input must be string|resource') extends InvalidArgumentException implements Exception\FilterException {
            };
        }
        if ($output !== null && !is_resource($output)) {
            throw new class ('$output must be resource|null') extends InvalidArgumentException implements Exception\FilterException {
            };
        }
        if ($error !== null && !is_resource($error)) {
            throw new class ('$error must be resource|null') extends InvalidArgumentException implements Exception\FilterException {
            };
        }
    }

    abstract protected function clone(): static;
}
