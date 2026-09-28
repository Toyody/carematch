<?php

namespace App\Console\Commands;

use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Console\Command;

final class ProvisionPublicDemo extends Command
{
    private const string CONFIRMATION = 'PROVISION_SYNTHETIC_PUBLIC_DEMO';

    /** @var string */
    protected $signature = 'carematch:demo:provision {--confirm= : Required safety acknowledgement}';

    /** @var string */
    protected $description = 'Provision the synthetic CareMatch public portfolio demo dataset';

    public function handle(): int
    {
        if (! app()->environment('production')) {
            $this->components->error('This command is reserved for the production public-demo environment.');

            return self::FAILURE;
        }

        if (config('carematch.portfolio_demo.public_mode') !== true) {
            $this->components->error('Set CARE_MATCH_PUBLIC_DEMO=true before provisioning the public demo.');

            return self::FAILURE;
        }

        if ($this->option('confirm') !== self::CONFIRMATION) {
            $this->components->error('Pass --confirm='.self::CONFIRMATION.' to acknowledge the guarded production write.');

            return self::FAILURE;
        }

        $seeder = new PortfolioDemoSeeder;
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        $seeder->provision();

        return self::SUCCESS;
    }
}
