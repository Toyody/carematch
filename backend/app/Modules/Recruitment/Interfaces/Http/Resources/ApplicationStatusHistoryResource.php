<?php

namespace App\Modules\Recruitment\Interfaces\Http\Resources;

use App\Modules\Recruitment\Application\Data\ApplicationStatusHistoryRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApplicationStatusHistoryRecord */
final class ApplicationStatusHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ApplicationStatusHistoryRecord $history */
        $history = $this->resource;

        return [
            'id' => $history->id,
            'from_status' => $history->fromStatus?->value,
            'to_status' => $history->toStatus->value,
            'changed_by_user_id' => $history->changedByUserId,
            'note' => $history->note,
            'created_at' => $history->createdAt->format(DATE_ATOM),
        ];
    }
}
