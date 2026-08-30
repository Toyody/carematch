<?php

namespace Tests\Unit\Modules\Identity\Application\Support;

use App\Modules\Identity\Application\Support\EmailNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailNormalizerTest extends TestCase
{
    #[DataProvider('emailProvider')]
    public function test_it_trims_and_lowercases_email_input(string $input, string $expected): void
    {
        $normalizer = new EmailNormalizer;

        self::assertSame($expected, $normalizer->normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function emailProvider(): iterable
    {
        yield 'mixed case and surrounding whitespace' => [
            " \tPerson@Example.COM\n",
            'person@example.com',
        ];

        yield 'already normalised' => [
            'person@example.com',
            'person@example.com',
        ];
    }
}
