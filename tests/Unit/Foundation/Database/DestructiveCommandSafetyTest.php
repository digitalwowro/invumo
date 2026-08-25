<?php

use App\Foundation\Database\DestructiveCommandSafety;

test('destructive database commands require isolated testing databases', function (): void {
    $safety = new DestructiveCommandSafety;

    expect($safety->permits('testing', ['invumo_test', 'invumo_test']))->toBeTrue()
        ->and($safety->permits('testing', ['invumo', 'invumo_test']))->toBeFalse()
        ->and($safety->permits('testing', ['invumo_test', 'invumo']))->toBeFalse()
        ->and($safety->permits('production', ['invumo_test', 'invumo_test']))->toBeFalse()
        ->and($safety->permits('local', ['invumo_test', 'invumo_test']))->toBeFalse()
        ->and($safety->permits('testing', []))->toBeFalse()
        ->and($safety->permits('testing', ['']))->toBeFalse()
        ->and($safety->permits('testing', [null]))->toBeFalse();
});
