<?php

namespace Tests\Feature;

use Database\Seeders\DemoAccountsSeeder;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DemoAccountsSeederSafetyTest extends TestCase
{
    #[Test]
    public function demo_account_setup_is_rejected_outside_the_local_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('restricted to the local environment');

        (new DemoAccountsSeeder)->run();
    }
}
