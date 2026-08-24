<?php

it('protects the English design-system matrix on desktop', function () {
    $page = visit('/__design-system/en')->on()->desktop();

    $page->assertSee('Invumo component system')
        ->assertSee('Operational table')
        ->assertSee('File upload states')
        ->assertSee('B/8 · O/0/D · 1/I/l')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('getComputedStyle(document.body).fontFamily'))
        ->toContain('Atkinson Hyperlegible Next');
    expect($page->script("getComputedStyle(document.querySelector('[data-slot=metric-value]')).fontFamily"))
        ->toContain('Atkinson Hyperlegible Mono');

    // PNG rasterization differs across operating systems. GitHub's pinned
    // Ubuntu runner owns the reference while every runner keeps semantic checks.
    if (getenv('CANONICAL_VISUAL_SNAPSHOTS') === 'true') {
        $page->assertScreenshotMatches();
    }
});

it('protects Romanian expansion and diacritics on desktop', function () {
    $page = visit('/__design-system/ro')
        ->on()->desktop()
        ->assertSee('Sistemul de componente Invumo')
        ->assertSee('Stările încărcării de fișiere')
        ->assertSee('ă â î ș ț')
        ->assertNoJavaScriptErrors();

    if (getenv('CANONICAL_VISUAL_SNAPSHOTS') === 'true') {
        $page->assertScreenshotMatches();
    }
});

it('protects the responsive navigation on a narrow viewport', function () {
    $page = visit('/__design-system/en')
        ->on()->iPhone15()
        ->assertSee('Invumo component system')
        ->click('Open navigation')
        ->assertSee('Dashboard')
        ->assertNoJavaScriptErrors();

    if (getenv('CANONICAL_VISUAL_SNAPSHOTS') === 'true') {
        $page->assertScreenshotMatches(fullPage: false);
    }
});

it('protects the shared confirmation overlay', function () {
    $page = visit('/__design-system/en')
        ->on()->desktop()
        ->click('Open confirmation')
        ->assertSee('Delete this draft?')
        ->assertNoJavaScriptErrors();

    if (getenv('CANONICAL_VISUAL_SNAPSHOTS') === 'true') {
        $page->assertScreenshotMatches(fullPage: false);
    }
});
