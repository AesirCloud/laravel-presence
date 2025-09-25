<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

// Ensure we're using the Laravel test environment
beforeEach(function () {
    // This ensures the application is properly bootstrapped before each test
    $this->app->make('config')->set('app.env', 'testing');
});