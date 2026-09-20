<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\OrganisationSummary;

interface OrganisationCreator
{
    public function createWithInitialAdmin(string $name, int $creatorUserId): OrganisationSummary;
}
