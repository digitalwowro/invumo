<?php

namespace App\Modules\Companies\Support;

final class CompanyLogoRasterStructure
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /** @var list<int> */
    private const JPEG_START_OF_FRAME_MARKERS = [
        0xC0, 0xC1, 0xC2, 0xC3,
        0xC5, 0xC6, 0xC7,
        0xC9, 0xCA, 0xCB,
        0xCD, 0xCE, 0xCF,
    ];

    public function isValid(string $contents, string $mimeType): bool
    {
        return match ($mimeType) {
            'image/png' => $this->isValidPng($contents),
            'image/jpeg' => $this->isValidJpeg($contents),
            'image/webp' => $this->isValidWebp($contents),
            default => false,
        };
    }

    private function isValidPng(string $contents): bool
    {
        $length = strlen($contents);

        if ($length < 45 || ! str_starts_with($contents, self::PNG_SIGNATURE)) {
            return false;
        }

        $offset = 8;
        $seenHeader = false;
        $seenImageData = false;

        while ($offset + 12 <= $length) {
            $chunkLength = unpack('Nvalue', substr($contents, $offset, 4));

            if ($chunkLength === false || $chunkLength['value'] > $length - $offset - 12) {
                return false;
            }

            $dataLength = $chunkLength['value'];
            $type = substr($contents, $offset + 4, 4);
            $chunkEnd = $offset + 12 + $dataLength;
            $expectedCrc = substr($contents, $offset + 8 + $dataLength, 4);
            $actualCrc = hash('crc32b', substr($contents, $offset + 4, 4 + $dataLength), true);

            if (! hash_equals($expectedCrc, $actualCrc)) {
                return false;
            }

            if (! $seenHeader && ($type !== 'IHDR' || $dataLength !== 13)) {
                return false;
            }

            if ($type === 'IHDR') {
                if ($seenHeader) {
                    return false;
                }

                $seenHeader = true;
            }

            if ($type === 'acTL') {
                return false;
            }

            if ($type === 'IDAT') {
                if (! $seenHeader) {
                    return false;
                }

                $seenImageData = true;
            }

            if ($type === 'IEND') {
                return $dataLength === 0
                    && $seenImageData
                    && $chunkEnd === $length;
            }

            $offset = $chunkEnd;
        }

        return false;
    }

    private function isValidJpeg(string $contents): bool
    {
        $length = strlen($contents);

        if ($length < 4 || ! str_starts_with($contents, "\xFF\xD8")) {
            return false;
        }

        $offset = 2;
        $inScan = false;
        $seenFrame = false;
        $seenScan = false;

        while ($offset < $length) {
            if ($contents[$offset] !== "\xFF") {
                if (! $inScan) {
                    return false;
                }

                $offset++;

                continue;
            }

            while ($offset < $length && $contents[$offset] === "\xFF") {
                $offset++;
            }

            if ($offset >= $length) {
                return false;
            }

            $marker = ord($contents[$offset]);
            $offset++;

            if ($inScan && ($marker === 0x00 || ($marker >= 0xD0 && $marker <= 0xD7))) {
                continue;
            }

            $inScan = false;

            if ($marker === 0xD9) {
                return $seenFrame && $seenScan && $offset === $length;
            }

            if ($marker === 0x01) {
                continue;
            }

            if ($marker === 0x00 || $marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                return false;
            }

            if ($offset + 2 > $length) {
                return false;
            }

            $segmentLength = unpack('nvalue', substr($contents, $offset, 2));

            if (
                $segmentLength === false
                || $segmentLength['value'] < 2
                || $segmentLength['value'] > $length - $offset
            ) {
                return false;
            }

            if (in_array($marker, self::JPEG_START_OF_FRAME_MARKERS, true)) {
                $seenFrame = true;
            }

            if ($marker === 0xDA) {
                $seenScan = true;
                $inScan = true;
            }

            $offset += $segmentLength['value'];
        }

        return false;
    }

    private function isValidWebp(string $contents): bool
    {
        $length = strlen($contents);

        if (
            $length < 20
            || substr($contents, 0, 4) !== 'RIFF'
            || substr($contents, 8, 4) !== 'WEBP'
        ) {
            return false;
        }

        $riffLength = unpack('Vvalue', substr($contents, 4, 4));

        if ($riffLength === false || $riffLength['value'] !== $length - 8) {
            return false;
        }

        $offset = 12;
        $seenImageData = false;

        while ($offset + 8 <= $length) {
            $type = substr($contents, $offset, 4);
            $chunkLength = unpack('Vvalue', substr($contents, $offset + 4, 4));

            if ($chunkLength === false || $chunkLength['value'] > $length - $offset - 8) {
                return false;
            }

            $dataLength = $chunkLength['value'];
            $paddedLength = $dataLength + ($dataLength % 2);

            if ($paddedLength > $length - $offset - 8) {
                return false;
            }

            if ($type === 'ANIM' || $type === 'ANMF') {
                return false;
            }

            if ($type === 'VP8X' && $dataLength > 0 && (ord($contents[$offset + 8]) & 0x02) !== 0) {
                return false;
            }

            if (in_array($type, ['VP8 ', 'VP8L', 'VP8X'], true)) {
                $seenImageData = true;
            }

            $offset += 8 + $paddedLength;
        }

        return $seenImageData && $offset === $length;
    }
}
