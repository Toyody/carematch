<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

final class PerformanceTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('performance')) {
            throw new RuntimeException('PerformanceTestSeeder may run only in the disposable performance environment.');
        }

        if (config('carematch.portfolio_demo.enabled') !== true) {
            throw new RuntimeException('Synthetic performance data must be explicitly enabled.');
        }

        $this->call(PortfolioDemoSeeder::class);
    }
}
