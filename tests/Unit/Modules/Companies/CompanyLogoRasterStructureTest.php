<?php

namespace Tests\Unit\Modules\Companies;

use App\Modules\Companies\Support\CompanyLogoRasterStructure;
use PHPUnit\Framework\TestCase;

final class CompanyLogoRasterStructureTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_near_maximum_jpeg_scan_uses_bounded_native_traversal(): void
    {
        $jpeg = $this->jpeg();
        $targetBytes = (5 * 1024 * 1024) - 1;
        $jpeg = substr($jpeg, 0, -2)
            .str_repeat("\x01", $targetBytes - strlen($jpeg))
            ."\xFF\xD9";
        $validator = new CompanyLogoRasterStructure;
        $startedAt = hrtime(true);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->assertTrue($validator->isValid($jpeg, 'image/jpeg'));
        }

        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

        $this->assertLessThan(
            500,
            $elapsedMilliseconds,
            'Ten near-5 MiB JPEG scans must not be traversed byte-by-byte in PHP.',
        );
    }

    public function test_container_element_budget_rejects_marker_and_chunk_bombs(): void
    {
        $jpeg = $this->jpeg();
        $jpegBomb = substr($jpeg, 0, 2)
            .str_repeat("\xFF\xFE\x00\x02", 4097)
            .substr($jpeg, 2);
        $png = $this->png();
        $pngBomb = substr($png, 0, 33)
            .str_repeat($this->pngChunk('ruSt', ''), 4097)
            .substr($png, 33);
        $webp = $this->webp();
        $webpBomb = substr($webp, 0, 12)
            .str_repeat('ruSt'.pack('V', 0), 4097)
            .substr($webp, 12);
        $webpBomb = substr_replace($webpBomb, pack('V', strlen($webpBomb) - 8), 4, 4);
        $validator = new CompanyLogoRasterStructure;

        $this->assertFalse($validator->isValid($jpegBomb, 'image/jpeg'));
        $this->assertFalse($validator->isValid($pngBomb, 'image/png'));
        $this->assertFalse($validator->isValid($webpBomb, 'image/webp'));
    }

    public function test_progressive_and_byte_stuffed_jpeg_scans_remain_accepted(): void
    {
        $validator = new CompanyLogoRasterStructure;
        $stuffedScan = substr($this->jpeg(), 0, -2)
            .str_repeat("\xFF\x00\xFF\xD0", 100)
            ."\xFF\xD9";

        $this->assertTrue($validator->isValid($this->jpeg(progressive: true), 'image/jpeg'));
        $this->assertTrue($validator->isValid($stuffedScan, 'image/jpeg'));
    }

    public function test_bytes_after_png_and_jpeg_terminators_are_rejected(): void
    {
        $validator = new CompanyLogoRasterStructure;

        $this->assertFalse($validator->isValid($this->png()."\0", 'image/png'));
        $this->assertFalse($validator->isValid($this->jpeg()."\0", 'image/jpeg'));
    }

    private function png(): string
    {
        return base64_decode(self::PNG_1X1, true)
            ?: throw new \RuntimeException('Invalid PNG fixture.');
    }

    private function jpeg(bool $progressive = false): string
    {
        $image = imagecreatetruecolor(4, 3);

        if ($image === false) {
            throw new \RuntimeException('Unable to create JPEG fixture.');
        }

        imageinterlace($image, $progressive);
        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();

        return is_string($contents)
            ? $contents
            : throw new \RuntimeException('Unable to encode JPEG fixture.');
    }

    private function webp(): string
    {
        $image = imagecreatetruecolor(4, 3);

        if ($image === false) {
            throw new \RuntimeException('Unable to create WebP fixture.');
        }

        ob_start();
        imagewebp($image);
        $contents = ob_get_clean();

        return is_string($contents)
            ? $contents
            : throw new \RuntimeException('Unable to encode WebP fixture.');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    }
}
