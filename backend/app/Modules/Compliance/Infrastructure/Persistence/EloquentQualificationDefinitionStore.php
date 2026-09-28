<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use App\Modules\Compliance\Application\Contracts\QualificationDefinitionReferences;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionStore;
use App\Modules\Compliance\Application\Data\QualificationDefinitionData;
use App\Modules\Compliance\Application\Data\QualificationDefinitionRecord;
use Carbon\CarbonImmutable;

final class EloquentQualificationDefinitionStore implements QualificationDefinitionReferences, QualificationDefinitionStore
{
    public function all(int $organisationId): array
    {
        return array_values(QualificationDefinition::query()
            ->where('organisation_id', $organisationId)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (QualificationDefinition $definition): QualificationDefinitionRecord => $this->record($definition))
            ->all());
    }

    public function create(int $organisationId, QualificationDefinitionData $data): QualificationDefinitionRecord
    {
        $definition = QualificationDefinition::query()->create([
            'organisation_id' => $organisationId,
            'name' => $data->name,
            'category' => $data->category,
            'description' => $data->description,
            'is_active' => $data->isActive,
        ]);

        return $this->record($definition);
    }

    public function update(int $organisationId, int $definitionId, QualificationDefinitionData $data): ?QualificationDefinitionRecord
    {
        $definition = QualificationDefinition::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($definitionId)
            ->first();
        if ($definition === null) {
            return null;
        }

        $definition->update([
            'name' => $data->name,
            'category' => $data->category,
            'description' => $data->description,
            'is_active' => $data->isActive,
        ]);

        return $this->record($definition);
    }

    public function activeExists(int $organisationId, int $definitionId): bool
    {
        return QualificationDefinition::query()->where('organisation_id', $organisationId)
            ->whereKey($definitionId)->where('is_active', true)->exists();
    }

    /**
     * @param  list<int>  $definitionIds
     * @return array<int, string>
     */
    public function names(int $organisationId, array $definitionIds): array
    {
        if ($definitionIds === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = QualificationDefinition::query()->where('organisation_id', $organisationId)
            ->whereIn('id', $definitionIds)->pluck('name', 'id')->all();

        return $names;
    }

    private function record(QualificationDefinition $definition): QualificationDefinitionRecord
    {
        return new QualificationDefinitionRecord(
            id: (int) $definition->getKey(),
            name: (string) $definition->getAttribute('name'),
            category: $definition->getAttribute('category'),
            description: $definition->getAttribute('description'),
            isActive: (bool) $definition->getAttribute('is_active'),
            createdAt: CarbonImmutable::instance($definition->getAttribute('created_at')),
            updatedAt: CarbonImmutable::instance($definition->getAttribute('updated_at')),
        );
    }
}
