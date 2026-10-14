<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class AcceptInvitation
{
    /**
     * @param  array{name?: string, password?: string, password_confirmation?: string}  $input
     *                                                                                          Wajib diisi hanya bila email undangan belum punya akun.
     */
    public function handle(string $token, array $input = []): User
    {
        return DB::transaction(function () use ($token, $input) {
            $invitation = Invitation::withoutGlobalScopes()
                ->where('token_hash', Invitation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isUsable()) {
                throw ValidationException::withMessages(['token' => 'Undangan tidak valid atau sudah kedaluwarsa.']);
            }

            $user = User::query()->where('email', $invitation->email)->first();

            if ($user === null) {
                $data = Validator::make($input, [
                    'name' => ['required', 'string', 'max:120'],
                    'password' => ['required', 'confirmed', Password::defaults()],
                ])->validate();

                // Email terbukti dimiliki (token dikirim ke sana), jadi langsung terverifikasi.
                $user = (new User())->forceFill([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($data['password']),
                    'is_internal' => Roles::isInternal($invitation->role),
                    'email_verified_at' => now(),
                ]);
                $user->save();
            }

            Membership::firstOrCreate([
                'user_id' => $user->getKey(),
                'company_id' => $invitation->company_id,
                'role' => $invitation->role,
                'scope_type' => $invitation->scope_type,
                'scope_id' => $invitation->scope_id,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            AuditLogger::log(
                'invitation.accepted',
                $invitation,
                ['role' => $invitation->role],
                $invitation->company_id,
                $user,
            );

            return $user;
        });
    }
}
