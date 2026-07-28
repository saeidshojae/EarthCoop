<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExperienceField;

class ExperienceFieldsSeeder extends Seeder
{
    public function run()
    {
        $data = include database_path('data/experience_fields_data.php');
        $this->seedExperienceFields($data);
    }

    private function seedExperienceFields(array $fields, $parentId = null)
    {
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $field = ExperienceField::create([
                    'name' => $key,
                    'parent_id' => $parentId,
                    'status' => 1,
                ]);
                $this->seedExperienceFields($value, $field->id);
            } else {
                ExperienceField::create([
                    'name' => $value,
                    'parent_id' => $parentId,
                    'status' => 1,
                ]);
            }
        }
    }
}
