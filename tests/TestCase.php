<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sanctum only treats a request as stateful when its Referer or Origin
     * matches sanctum.stateful, and only then is a session started. Browsers
     * always send one; the test client does not, so without this every request
     * would run sessionless and nothing that touches the session would work.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', (string) config('app.frontend_url'));
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
