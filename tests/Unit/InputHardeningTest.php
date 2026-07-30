<?php

declare(strict_types=1);

namespace Wazum\Stipple\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Stipple\Exception\InvalidArgumentException;
use Wazum\Stipple\Exception\InvalidSvgException;
use Wazum\Stipple\Stipple;
use Wazum\Stipple\SvgPreprocessor;

final class InputHardeningTest extends TestCase
{
    private SvgPreprocessor $preprocessor;

    protected function setUp(): void
    {
        $this->preprocessor = new SvgPreprocessor();
    }

    /**
     * The DOCTYPE pre-scan reads raw bytes, so any encoding libxml auto-detects but the regex
     * cannot see slips past it. The authoritative check has to happen after parsing.
     */
    #[Test]
    #[DataProvider('reencodedDoctypeProvider')]
    public function doctypeIsRejectedWhateverTheEncoding(string $svg): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean($svg, null);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function reencodedDoctypeProvider(): iterable
    {
        $doc = '<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" fill="#fff"/></svg>';

        yield 'plain utf-8' => [$doc];

        $utf16le = mb_convert_encoding($doc, 'UTF-16LE', 'UTF-8');
        self::assertIsString($utf16le);
        yield 'utf-16le with bom' => ["\xFF\xFE".$utf16le];

        $utf16be = mb_convert_encoding($doc, 'UTF-16BE', 'UTF-8');
        self::assertIsString($utf16be);
        yield 'utf-16be with bom' => ["\xFE\xFF".$utf16be];

        $declared = '<?xml version="1.0" encoding="UTF-16"?>'.$doc;
        $declaredUtf16 = mb_convert_encoding($declared, 'UTF-16LE', 'UTF-8');
        self::assertIsString($declaredUtf16);
        yield 'utf-16 by declaration' => [$declaredUtf16];
    }

    #[Test]
    public function validUtf16SvgWithoutADoctypeStillRenders(): void
    {
        $doc = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" fill="#ffffff"/></svg>';
        $utf16 = mb_convert_encoding($doc, 'UTF-16LE', 'UTF-8');
        self::assertIsString($utf16);

        $result = $this->preprocessor->clean("\xFF\xFE".$utf16, null);

        self::assertSame(1.0, $result->aspectRatio);
    }

    /**
     * Curve and arc approximation cost scales with coordinate magnitude, so coordinates far
     * outside the viewBox exhaust memory with an uncatchable fatal — from a tiny document.
     */
    #[Test]
    #[DataProvider('runawayCoordinateProvider')]
    public function runawayPathCoordinatesAreRejected(string $pathData): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<path d="'.$pathData.'" fill="none" stroke="#ffffff"/></svg>',
            null,
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function runawayCoordinateProvider(): iterable
    {
        yield 'cubic control point' => ['M0 0 C 1000000 0, 0 1000000, 16 16'];
        yield 'quadratic control point' => ['M0 0 Q 5000000 5000000, 16 16'];
        yield 'arc endpoint' => ['M0 0 A 8 8 0 0 1 1000000 1000000'];
        yield 'line endpoint' => ['M0 0 L 99999999 0'];
    }

    #[Test]
    public function coordinatesModestlyOutsideTheViewBoxAreStillAccepted(): void
    {
        // Icons legitimately overshoot their viewBox a little; only runaway values are refused.
        $result = $this->preprocessor->clean(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<path d="M-8 -8 C 24 -8, 24 24, -8 24z" fill="#ffffff"/></svg>',
            null,
        );

        self::assertStringContainsString('path', $result->svg);
    }

    #[Test]
    public function oversizedFilesAreRefusedBeforeBeingRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stipple');
        self::assertIsString($path);
        file_put_contents($path, str_repeat(' ', 5 * 1024 * 1024));

        try {
            $this->expectException(InvalidArgumentException::class);
            Stipple::make($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * A CLI enumerating an untrusted directory prints these straight to a TTY, so a filename
     * carrying terminal control sequences must not survive into the message.
     */
    #[Test]
    public function controlCharactersInAPathAreNotEchoedRaw(): void
    {
        try {
            Stipple::make("/nope/\e]0;pwned\x07icon.svg");
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString("\e", $exception->getMessage());
            self::assertStringNotContainsString("\x07", $exception->getMessage());
        }
    }
}
