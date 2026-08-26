<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use RuntimeException;

final class PrivateSqlBackupFiles
{
    public function prepareDirectory(string $directory): string
    {
        $directory = rtrim($directory, '/');
        umask(0077);

        if ($directory === '' || $directory === '/' || ! str_starts_with($directory, '/')) {
            throw new RuntimeException('The private backup directory must be a narrow absolute path.');
        }

        if (is_link($directory)) {
            throw new RuntimeException('The private backup directory cannot be a symbolic link.');
        }

        if (! is_dir($directory)
            && ! mkdir($directory, 0700, true)
            && ! is_dir($directory)) {
            throw new RuntimeException('The private backup directory could not be created.');
        }

        if (! chmod($directory, 0700)) {
            throw new RuntimeException('The private backup directory permissions could not be enforced.');
        }

        return $directory;
    }

    /** @return resource */
    public function open(string $temporaryPath)
    {
        $destination = fopen($temporaryPath, 'xb');

        if (! is_resource($destination)) {
            throw new RuntimeException('The private SQL backup file could not be created.');
        }

        return $destination;
    }

    /** @return array{path: string, bytes: int, sha256: string} */
    public function finalize(string $temporaryPath, string $finalPath, string $directory): array
    {
        $size = filesize($temporaryPath);
        $header = file_get_contents($temporaryPath, false, null, 0, 256);

        if (! is_int($size) || $size <= 0
            || ! is_string($header)
            || ! str_contains($header, 'PostgreSQL database dump')) {
            $this->removePartial($temporaryPath, $directory);
            throw new RuntimeException('The generated SQL backup failed integrity checks.');
        }

        if (! chmod($temporaryPath, 0600) || ! rename($temporaryPath, $finalPath)) {
            $this->removePartial($temporaryPath, $directory);
            throw new RuntimeException('The SQL backup could not be finalized securely.');
        }

        $checksum = hash_file('sha256', $finalPath);

        if (! is_string($checksum)) {
            throw new RuntimeException('The SQL backup checksum could not be calculated.');
        }

        return ['path' => $finalPath, 'bytes' => $size, 'sha256' => $checksum];
    }

    public function removePartial(string $path, string $directory): void
    {
        if (is_file($path)
            && dirname($path) === $directory
            && str_contains(basename($path), '.partial-')) {
            unlink($path);
        }
    }
}
