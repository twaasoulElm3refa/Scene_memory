<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledViews = sys_get_temp_dir().'/scemory-compiled-views';

        if (! is_dir($compiledViews)) {
            mkdir($compiledViews, 0777, true);
        }

        config()->set('view.compiled', $compiledViews);
    }
}
