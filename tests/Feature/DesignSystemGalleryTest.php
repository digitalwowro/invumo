<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the development design-system gallery in each supported locale', function (string $locale, string $title) {
    $this->get(route('design-system.gallery', ['locale' => $locale]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('design-system/gallery')
            ->where('i18n.locale', $locale)
            ->where('gallery.page.title', $title)
            ->has('gallery.sections')
            ->has('gallery.feedback')
            ->has('gallery.table'));
})->with([
    ['en', 'Invumo component system'],
    ['ro', 'Sistemul de componente Invumo'],
]);

it('rejects unsupported design-system gallery locales', function () {
    $this->get('/__design-system/fr')->assertNotFound();
});
