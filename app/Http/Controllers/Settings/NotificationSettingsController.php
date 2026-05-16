<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        $preferences = $user->notification_preferences ?? $user->getDefaultNotificationPreferences();

        return Inertia::render('settings/Notifications', [
            'preferences' => $preferences,
            'defaultPreferences' => $user->getDefaultNotificationPreferences(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.email' => ['boolean'],
            'preferences.*.sms' => ['boolean'],
        ]);

        $request->user()->update([
            'notification_preferences' => $validated['preferences'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notification preferences updated successfully.']);

        return to_route('notifications.edit');
    }
}
