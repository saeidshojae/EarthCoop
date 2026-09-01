<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $setting = Setting::singleton();
        $setting->fill([
            'invation_status' => true,
            'finger_status' => false,
            'expire_invation_time' => 72,
            'count_invation' => 10,
            'najm_summary' => null,
            'welcome_titre' => null,
            'welcome_content' => null,
            'home_titre' => null,
            'home_content' => null,
            'najm_bahar_user_threshold' => 2,
            'najm_bahar_initial_amount' => 1000000,
            'najm_bahar_initial_active_percentage' => 30,
            'najm_bahar_initial_active_type' => 'percentage',
            'najm_bahar_initial_active_fixed_amount' => 0,
            'najm_bahar_auto_activation_enabled' => false,
            'najm_bahar_auto_activation_period' => 'monthly',
            'najm_bahar_auto_activation_amount' => 0,
            'reputation_to_gol_ratio' => 100,
            'reputation_conversion_enabled' => true,
            'najm_bahar_membership_fee_account' => '0000000000-001',
            'najm_bahar_membership_fee_insurance_account' => '0000000000-002',
            'najm_bahar_membership_fee_burn_account' => '0000000000-000',
            'najm_bahar_membership_fee_amount' => 1200,
            'najm_bahar_membership_fee_membership_amount' => 600,
            'najm_bahar_membership_fee_insurance_amount' => 300,
            'najm_bahar_membership_fee_burn_amount' => 300,
        ]);
        $setting->najm_bahar_amounts_in_gol = true;
        $setting->save();
    }
}
