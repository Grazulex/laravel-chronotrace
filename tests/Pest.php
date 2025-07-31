<?php

declare(strict_types=1);

use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;
use Orchestra\Testbench\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Configure the package for testing
uses()->beforeEach(function (): void {
    $this->app->register(LaravelChronotraceServiceProvider::class);
})->in('Feature', 'Unit');
