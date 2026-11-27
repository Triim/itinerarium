<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.testing.page_paths', [
            resource_path('js/pages'),
        ]);

        // Every test starts with an empty fake public disk, so no test can write to the real storage
        // or see files left by an earlier test.
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The image tests leave cyclic garbage behind (decoded images, media models). Collected
        // after every test, it cannot pile up until a large upload runs out of the 128 MB limit.
        gc_collect_cycles();
    }
}
