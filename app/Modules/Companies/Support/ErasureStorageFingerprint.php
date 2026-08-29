<?php

namespace App\Modules\Companies\Support;

use InvalidArgumentException;

final readonly class ErasureStorageFingerprint
{
    public function for(string $disk): string
    {
        $configuration = config("filesystems.disks.{$disk}");

        if (! is_array($configuration) || ! is_string($configuration['driver'] ?? null)) {
            throw new InvalidArgumentException('Erasure cleanup requires a configured storage disk.');
        }

        $location = array_intersect_key($configuration, array_flip([
            'driver',
            'root',
            'bucket',
            'endpoint',
            'url',
            'region',
            'use_path_style_endpoint',
        ]));
        ksort($location);

        return hash('sha256', json_encode($location, JSON_THROW_ON_ERROR));
    }
}
