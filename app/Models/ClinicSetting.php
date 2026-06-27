<?php

namespace App\Models;

use App\Concerns\Cachable;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicSetting extends BaseModel
{
    use Cachable;

    protected string $cachePrefix = 'clinic_settings';

    protected $casts = [
        'clinic_id' => 'integer',
        'value' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getGroupSettings(int $clinicId, string $group): array
    {
        $settings = static::query()
            ->withoutClinicScope()
            ->where('clinic_id', $clinicId)
            ->where('group', $group)
            ->get();

        $result = [];
        $defaults = static::defaults($group);

        foreach ($settings as $setting) {
            $result[$setting->key] = static::normalizeStoredValue(
                $setting->value,
                $defaults[$setting->key] ?? null,
            );
        }

        return $result;
    }

    public static function get(int $clinicId, string $group, string $key, mixed $default = null): mixed
    {
        $setting = static::query()
            ->withoutClinicScope()
            ->where('clinic_id', $clinicId)
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        return $setting !== null
            ? static::normalizeStoredValue($setting->value, $default)
            : $default;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function setGroup(int $clinicId, string $group, array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::query()->withoutClinicScope()->updateOrCreate(
                [
                    'clinic_id' => $clinicId,
                    'group' => $group,
                    'key' => $key,
                ],
                [
                    'value' => is_array($value) ? $value : [$value],
                ],
            );
        }

        static::forgetCacheForClinic($clinicId, 'clinic_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(string $group): array
    {
        return match ($group) {
            'clinic' => [
                'name' => null,
                'logo_path' => null,
                'director_name' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'invoice_clinic_name' => null,
                'invoice_footer' => null,
                'invoice_default_notes' => null,
                'currency_syp' => 1,
                'currency_try' => 1,
                'currency_usd' => 1,
                'currency_iqd' => 1,
                'thousands_separator' => ',',
                'decimal_places' => 2,
            ],
            'appointments' => [
                'default_duration' => 30,
                'allow_outside_hours' => false,
                'allow_overlapping' => false,
                'max_per_doctor_per_day' => 30,
                'types' => [
                    ['name' => 'فحص أولي', 'is_default' => true],
                    ['name' => 'مراجعة', 'is_default' => true],
                ],
            ],
            'financial' => [
                'payment_methods' => ['cash', 'bank_transfer', 'card'],
                'salary_generation_day' => 1,
                'salary_due_date' => 5,
                'doctor_earning_mode' => 'appointment_only',
                'currency_display_format' => 'symbol',
                'rounding_rule' => 'none',
            ],
            'appearance' => [
                'theme' => 'system',
                'primary_color' => '#0EA5E9',
                'language' => 'ar',
                'font_size' => 'medium',
            ],
            'support' => [
                'company_name' => null,
                'support_phone' => null,
                'support_email' => null,
                'whatsapp_number' => null,
                'support_hours' => null,
                'license_info' => null,
                'app_version' => '1.0.0',
                'last_update_date' => null,
                'changelog' => null,
            ],
            default => [],
        };
    }

    private static function normalizeStoredValue(mixed $value, mixed $default = null): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (is_array($default)) {
            return $value;
        }

        if (array_is_list($value) && count($value) === 1) {
            return $value[0];
        }

        return $value;
    }
}
