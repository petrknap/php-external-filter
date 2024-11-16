<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use PHPUnit\Framework\Attributes\DataProvider;

final class ExternalFilterTest extends PipelinableFilterTestCase
{
    #[DataProvider('dataFilterThrowsWhenProcessFailed')]
    public function testFilterThrowsWhenProcessFailed(string $command, array $options, string $input): void
    {
        self::expectException(Exception\FilterException::class);

        (new Filter($command, $options))->filter($input);
    }

    public static function dataFilterThrowsWhenProcessFailed(): array
    {
        return [
            'unknown command' => ['unknown', [], ''],
            'unknown option' => ['php', ['--unknown'], ''],
            'wrong data' => ['php', [], '<?php wrong data'],
        ];
    }

    protected static function createPhpFilter(): PipelinableFilter
    {
        return new Filter('php');
    }

    protected static function createPassTroughFilter(): PipelinableFilter
    {
        return new Filter('cat');
    }
}
