<?php

namespace App\Observers;

use App\Actions\Rbac\SyncClinicRbacAction;
use App\Actions\Security\UpsertSecurityPolicyAction;
use App\Models\BrandingSetting;
use App\Models\Clinic;
use Illuminate\Support\Facades\Schema;

class ClinicObserver
{
    /**
     * Handle the Clinic "created" event.
     */
    public function created(Clinic $clinic): void
    {
        app(SyncClinicRbacAction::class)->handle((int) $clinic->id);

        if (Schema::hasTable('security_policies')) {
            app(UpsertSecurityPolicyAction::class)->handle((int) $clinic->id, null);
        }

        if (Schema::hasTable('branding_settings')) {
            BrandingSetting::query()->firstOrCreate(
                ['clinic_id' => $clinic->id],
                [
                    'company_name' => $clinic->name,
                    'logo_path' => null,
                    'theme_tokens' => null,
                    'locale_default' => config('app.locale', 'en'),
                    'domain' => null,
                ],
            );
        }
    }
}
