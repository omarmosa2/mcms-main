<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Security\CreateUserInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserInvitationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserInvitationController extends Controller
{
    public function __construct(private CreateUserInvitationAction $createUserInvitationAction) {}

    public function store(StoreUserInvitationRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = (int) $request->user()->clinic_id;

        $invitation = $this->createUserInvitationAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        $invitationUrl = route('register', ['invitation' => $invitation->token]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role_name' => $invitation->role_name,
                    'expires_at' => $invitation->expires_at?->toISOString(),
                    'invitation_url' => $invitationUrl,
                ],
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation generated successfully.']);

        return to_route('security.edit')->with('latest_invitation_url', $invitationUrl);
    }
}
