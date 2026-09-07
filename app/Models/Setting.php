<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\BaharMoney;

class Setting extends Model
{
    use HasFactory;
    protected $table = 'setting';
    protected $fillable = [
        'invation_status',
        'finger_status',
        'expire_invation_time',
        'count_invation',
        'najm_summary',
        'welcome_titre',
        'welcome_content',
        'home_titre',
        'home_content',
        'najm_bahar_user_threshold',
        'najm_bahar_initial_amount',
        'najm_bahar_initial_active_percentage',
        'najm_bahar_initial_active_type',
        'najm_bahar_initial_active_fixed_amount',
        'najm_bahar_auto_activation_enabled',
        'najm_bahar_auto_activation_period',
        'najm_bahar_auto_activation_amount',
        'najm_bahar_membership_fee_account',
        'najm_bahar_membership_fee_insurance_account',
        'najm_bahar_membership_fee_burn_account',
        'najm_bahar_membership_fee_amount',
        'najm_bahar_membership_fee_membership_amount',
        'najm_bahar_membership_fee_insurance_amount',
        'najm_bahar_membership_fee_burn_amount',
        'reputation_to_gol_ratio',
        'reputation_conversion_enabled',
    ];

    public static function singleton(): self
    {
        $settings = self::query()->find(1);
        if ($settings) {
            return $settings;
        }

        $settings = new self();
        $settings->forceFill(['id' => 1]);
        $settings->save();

        return $settings->fresh();
    }

    public static function firstNajmBaharSettings(): self
    {
        $settings = self::singleton();

        if (! $settings->najm_bahar_amounts_in_gol) {
            $fields = [
                'najm_bahar_initial_amount',
                'najm_bahar_membership_fee_amount',
                'najm_bahar_membership_fee_membership_amount',
                'najm_bahar_membership_fee_insurance_amount',
                'najm_bahar_membership_fee_burn_amount',
            ];

            foreach ($fields as $field) {
                if ($settings->{$field} !== null) {
                    $settings->{$field} = (int) $settings->{$field} * BaharMoney::GOL_PER_BAHAR;
                }
            }

            $settings->najm_bahar_amounts_in_gol = true;
            $settings->save();
        }

        return $settings;
    }
}
