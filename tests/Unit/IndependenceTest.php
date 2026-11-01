<?php

declare(strict_types=1);

/**
 * The add-on stands on its own: nothing it ships names the application it was split from.
 *
 * @command  vendor/bin/pest tests/Unit/IndependenceTest.php
 */
it('references no App class and no UAMS theme partial under src', function (): void {
    // Read every PHP file the add-on ships
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    // Check each for an application namespace
    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        // Expect no `App\` reference
        expect(file_get_contents($file->getPathname()))->not->toContain('use App\\')->not->toContain(' \\App\\')->not->toContain('(App\\');
    }
});

it('names no theme partial in its views', function (): void {
    // Read every view the add-on ships
    $views = glob(dirname(__DIR__, 2).'/resources/views/{,*/}*.antlers.html', GLOB_BRACE) ?: [];

    // Expect some views, and none that calls a UAMS theme partial
    expect($views)->not->toBeEmpty();

    foreach ($views as $view) {
        expect(file_get_contents($view))->not->toMatch('/\{\{\s*partial:(layout|page_builder|components|posts)\//')
            ->and(file_get_contents($view))->not->toContain('partial src="search/');
    }
});
