<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Organisation\Application\Contracts\OrganisationCreator;
use App\Modules\Organisation\Application\Data\OrganisationSummary;

final readonly class CreateOrganisation
{
    public function __construct(
        private OrganisationCreator $organisations,
    ) {}

    public function handle(string $name, int $creatorUserId): OrganisationSummary
    {
        return $this->organisations->createWithInitialAdmin($name, $creatorUserId);
    }
}
