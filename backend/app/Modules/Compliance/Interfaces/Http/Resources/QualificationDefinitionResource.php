<?php

namespace App\Modules\Compliance\Interfaces\Http\Resources;

use App\Modules\Compliance\Application\Data\QualificationDefinitionRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QualificationDefinitionRecord */
final class QualificationDefinitionResource extends JsonResource
{
    /** @return array<string, bool|int|string|null> */
    public function toArray(Request $request): array
    {
        /** @var QualificationDefinitionRecord $definition */
        $definition = $this->resource;

        return [
            'id' => $definition->id,
            'name' => $definition->name,
            'category' => $definition->category,
            'description' => $definition->description,
            'is_active' => $definition->isActive,
            'created_at' => $definition->createdAt->format(DATE_ATOM),
            'updated_at' => $definition->updatedAt->format(DATE_ATOM),
        ];
    }
}
