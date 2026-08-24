<?php

namespace Tests\Feature\Foundation\Validation;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocalizedValidationTest extends TestCase
{
    public function test_validation_messages_use_the_laravel_authored_locale_catalogue(): void
    {
        app()->setLocale('ro');

        $validator = Validator::make(
            ['email' => 'invalid'],
            ['email' => ['required', 'email']],
        );

        $this->assertSame(
            'Câmpul email trebuie să conțină o adresă de e-mail validă.',
            $validator->errors()->first('email'),
        );
    }
}
