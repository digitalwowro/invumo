<?php

namespace App\Modules\Identity\Data;

final readonly class DeleteUserData
{
    public function __construct(public string $stateVersion) {}
}
