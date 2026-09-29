<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionReferences;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionStore;
use App\Modules\Compliance\Application\Data\QualificationDefinitionData;
use App\Modules\Compliance\Application\Data\QualificationDefinitionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class EloquentQualificationDefinitionStore implements QualificationDefinitionReferences, QualificationDefinitionStore
{
    public function __construct(private AuditRecorder $audit) {}

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

    public function create(int $organisationId, int $actorUserId, QualificationDefinitionData $data): QualificationDefinitionRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $data): QualificationDefinitionRecord {
            $definition = QualificationDefinition::query()->create([
                'organisation_id' => $organisationId, 'name' => $data->name, 'category' => $data->category,
                'description' => $data->description, 'is_active' => $data->isActive,
            ]);
            $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'qualification_definition.created', 'qualification_definition', (int) $definition->getKey()));

            return $this->record($definition);
        });
    }

    public function update(int $organisationId, int $actorUserId, int $definitionId, QualificationDefinitionData $data): ?QualificationDefinitionRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $definitionId, $data): ?QualificationDefinitionRecord {
            $definition = QualificationDefinition::query()->where('organisation_id', $organisationId)
                ->whereKey($definitionId)->lockForUpdate()->first();
            if ($definition === null) {
                return null;
            }
            $wasActive = (bool) $definition->getAttribute('is_active');
            $definition->fill([
                'name' => $data->name, 'category' => $data->category,
                'description' => $data->description, 'is_active' => $data->isActive,
            ]);
            $changedFields = array_keys($definition->getDirty());
            if ($changedFields !== []) {
                $definition->save();
                $eventType = $wasActive && ! $data->isActive
                    ? 'qualification_definition.deactivated'
                    : 'qualification_definition.updated';
                $this->audit->record(new AuditEvent($organisationId, $actorUserId, $eventType, 'qualification_definition', $definitionId, ['changed_fields' => $changedFields]));
            }

            return $this->record($definition);
        });
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
