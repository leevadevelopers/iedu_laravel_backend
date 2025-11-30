<?php

namespace App\Models\Traits;

use App\Services\SchoolContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait HasSchoolScope
{
    protected static function bootHasSchoolScope(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            try {
                $model = $builder->getModel();
                $table = $model->getTable();

                // Verificar se a tabela tem a coluna school_id antes de aplicar o scope
                if (!Schema::hasColumn($table, 'school_id')) {
                    return; // Não aplicar scope se a coluna não existir
                }

                $schoolContextService = app(SchoolContextService::class);
                $schoolId = $schoolContextService->getCurrentSchoolId();

                if ($schoolId) {
                    $builder->where($table . '.school_id', $schoolId);
                }
            } catch (\Exception $e) {
                // Log but don't break if school context is not available
                Log::warning('HasSchoolScope: Could not get school context', [
                    'error' => $e->getMessage(),
                    'model' => get_class($builder->getModel())
                ]);
            }
        });

        static::creating(function (Model $model) {
            if (!$model->school_id) {
                try {
                    $schoolContextService = app(SchoolContextService::class);
                    $schoolId = $schoolContextService->getCurrentSchoolId();

                    if ($schoolId) {
                        $model->school_id = $schoolId;
                        Log::info('HasSchoolScope: Set school_id on model creation', [
                            'school_id' => $schoolId,
                            'model' => get_class($model)
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('HasSchoolScope: Could not set school_id on model creation', [
                        'error' => $e->getMessage(),
                        'model' => get_class($model)
                    ]);
                }
            }
        });
    }

    public function scopeWithoutSchoolScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('school');
    }

    public function scopeForSchool(Builder $query, ?int $schoolId = null): Builder
    {
        if ($schoolId === null) {
            try {
                $schoolContextService = app(SchoolContextService::class);
                $schoolId = $schoolContextService->getCurrentSchoolId();
            } catch (\Exception $e) {
                Log::warning('HasSchoolScope: Could not get school context in scopeForSchool', [
                    'error' => $e->getMessage()
                ]);
                return $query;
            }
        }

        if ($schoolId) {
            return $query->where($this->getTable() . '.school_id', $schoolId);
        }

        return $query;
    }
}

