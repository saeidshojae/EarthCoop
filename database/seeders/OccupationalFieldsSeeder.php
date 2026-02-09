<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OccupationalField;

class OccupationalFieldsSeeder extends Seeder
{
    public function run()
    {
        $data = include database_path('data/occupational_fields_data.php');

        $this->seedOccupationalFields($data);
    }

    private function seedOccupationalFields(array $fields, $parentId = null)
    {
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                // $key = دسته اصلی یا زیرشاخه
                $field = OccupationalField::create([
                    'name' => $key,
                    'parent_id' => $parentId,
                    'status' => 1,
                ]);

                // ادامه بازگشتی برای زیردسته‌ها
                $this->seedOccupationalFields($value, $field->id);
            } else {
                // $value = یک ردیف نهایی (بدون زیرشاخه)
                OccupationalField::create([
                    'name' => $value,
                    'parent_id' => $parentId,
                    'status' => 1,
                ]);
            }
        }
    }
}
