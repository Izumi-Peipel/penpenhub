<?php

namespace App\Http\Controllers;

use App\Actions\AcceptInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = Invitation::findByToken($token);

        if ($invitation === null || ! $invitation->isUsable()) {
            return response()->view('invitations.invalid', [], 410);
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
            'hasAccount' => User::query()->where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvitation $action)
    {
        try {
            $user = $action->handle($token, $request->only('name', 'password', 'password_confirmation'));
        } catch (ValidationException $e) {
            if (array_key_exists('token', $e->errors())) {
                return response()->view('invitations.invalid', [], 410);
            }

            throw $e;
        }

        // Akun lama (mungkin ber-2FA) wajib login normal; hanya akun baru yang langsung masuk.
        if (! $user->wasRecentlyCreated) {
            return redirect()->route('login')->with('status', 'Undangan diterima. Silakan masuk.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(config('fortify.home', '/dashboard'));
    }
}
