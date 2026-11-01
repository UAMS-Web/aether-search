<?php

/**
 * Pest configuration for the add-on.
 *
 * Integration tests boot a minimal Statamic through Orchestra Testbench via
 * `Tests\TestCase`; Unit tests stay on PHPUnit's own base class and boot
 * nothing. Shared helpers and custom expectations go here when the suite
 * grows them.
 */

declare(strict_types=1);

use UAMSWeb\Aether\Search\Tests\OverridingTestCase;
use UAMSWeb\Aether\Search\Tests\TestCase;

pest()->extend(TestCase::class)->in('Integration');

// A host that overrides a card needs its view path before the provider boots, so those tests have a
// base class of their own.
pest()->extend(OverridingTestCase::class)->in('Overriding');
