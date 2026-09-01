<?php

namespace App\Services\ReferenceData;

use App\Models\Alley;
use App\Models\ExperienceField;
use App\Models\Neighborhood;
use App\Models\OccupationalField;
use App\Models\Region;
use App\Models\Rural;
use App\Models\Street;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReferenceDataApprovalService
{
    /** @return array<string,mixed> */
    public function approve(string $type, int $id): array
    {
        $model = $this->find($type, $id);
        if ((int) $model->getAttribute('status') === 1) {
            return ['type'=>$type,'id'=>$id,'status'=>'already_approved','users_updated'=>0];
        }

        return DB::transaction(function () use ($type, $model, $id): array {
            $model->update(['status' => 1]);
            $usersUpdated = match ($type) {
                'experience' => $this->updateExperienceUsers($id),
                'occupational' => $this->updateOccupationalUsers($id),
                default => 0,
            };
            return ['type'=>$type,'id'=>$id,'status'=>'approved','users_updated'=>$usersUpdated];
        });
    }

    public function find(string $type, int $id): Model
    {
        $class = $this->modelMap()[$type] ?? null;
        if (! $class) throw new InvalidArgumentException('unsupported_reference_type');
        return $class::query()->findOrFail($id);
    }

    /** @return array<string,class-string<Model>> */
    public function modelMap(): array
    {
        return [
            'experience'=>ExperienceField::class,'occupational'=>OccupationalField::class,
            'rural'=>Rural::class,'region'=>Region::class,'neighborhood'=>Neighborhood::class,
            'street'=>Street::class,'alley'=>Alley::class,
        ];
    }

    protected function updateExperienceUsers(int $id): int
    {
        $updated=0;
        foreach (DB::table('user_experience_field')->where('experience_field_id',$id)->pluck('user_id') as $userId) {
            $user=User::query()->find($userId);
            if ($user && $user->experiences()->where('status','!=',1)->count()===0 && (int)$user->experience_status!==1) {
                $user->update(['experience_status'=>1]); $updated++;
            }
        }
        return $updated;
    }

    protected function updateOccupationalUsers(int $id): int
    {
        $updated=0;
        foreach (DB::table('user_occupational_field')->where('occupational_field_id',$id)->pluck('user_id') as $userId) {
            $user=User::query()->find($userId);
            if ($user && $user->specialties()->where('status','!=',1)->count()===0 && (int)$user->occupational_status!==1) {
                $user->update(['occupational_status'=>1]); $updated++;
            }
        }
        return $updated;
    }
}
