<?php

namespace App\Modules\Recruitment\Application\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Recruitment\Domain\ApplicationStatus;

final class ApplicationTransitionPermissions
{
    public function allows(
        OrganisationRole $role,
        ApplicationStatus $current,
        ApplicationStatus $target,
    ): bool {
        return match ($role) {
            OrganisationRole::Admin, OrganisationRole::Recruiter => true,
            OrganisationRole::HiringManager => $current === ApplicationStatus::Interview
                && in_array($target, [ApplicationStatus::Offer, ApplicationStatus::Rejected], true),
        };
    }
}
