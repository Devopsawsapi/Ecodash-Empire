<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

// ════════════════════════════════════════════════════════════════
// AuthController — Authentification EcoDash (Laravel Sanctum)
// Fonctionnalités : login, logout, me, register, update, delete
// ════════════════════════════════════════════════════════════════

class AuthController extends Controller
{
    // ─── LOGIN ───────────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'        => 'required|email',
            'password'     => 'required',
            'device_token' => 'nullable|string',
        ]);

        if (! \Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Identifiants invalides.',
                'code'    => 'INVALID_CREDENTIALS',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = \Auth::user();

        if (! $user->actif) {
            return response()->json([
                'message' => 'Votre compte a été désactivé. Contactez un administrateur.',
                'code'    => 'ACCOUNT_DISABLED',
            ], 403);
        }

        // Mettre à jour le device token mobile si fourni
        if ($request->device_token) {
            $user->update(['device_token' => $request->device_token]);
        }

        // Révoquer les anciens tokens (optionnel : garder un seul token actif)
        // $user->tokens()->delete();

        $token = $user->createToken('ecodash-' . ($request->device_token ? 'mobile' : 'web'))->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    // ─── LOGOUT ──────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    // ─── LOGOUT ALL DEVICES ──────────────────────────────────────
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Déconnecté de tous les appareils.']);
    }

    // ─── MON PROFIL ──────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->formatUser($request->user())]);
    }

    // ─── METTRE À JOUR MON PROFIL ────────────────────────────────
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name'         => 'sometimes|string|max:100',
            'telephone'    => 'sometimes|nullable|string|max:20',
            'password'     => ['sometimes', 'confirmed', Password::min(8)],
            'device_token' => 'sometimes|nullable|string',
        ]);

        $data = $request->only(['name', 'telephone', 'device_token']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil mis à jour.',
            'data'    => $this->formatUser($user->fresh()),
        ]);
    }

    // ─── CHANGER MOT DE PASSE ────────────────────────────────────
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);
        // Invalider tous les tokens sauf le courant
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }

    // ─── FORMAT USER (données exposées) ──────────────────────────
    private function formatUser(User $user): array
    {
        return [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'telephone'        => $user->telephone,
            'matricule'        => $user->matricule,
            'role'             => $user->role,
            'role_label'       => $this->roleLabel($user->role),
            'modules_acces'    => $user->modules_acces ?? [],
            'zone_affectation' => $user->zone_affectation,
            'photo'            => $user->photo,
            'actif'            => $user->actif,
            'is_admin'         => $user->role === 'admin',
            'created_at'       => $user->created_at?->toISOString(),
        ];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin'            => 'Administrateur',
            'ingenieur_env'    => 'Ingénieur Environnement',
            'ingenieur_social' => 'Ingénieur Social',
            'technicien_eau'   => 'Technicien Eau',
            'technicien_air'   => 'Technicien Air',
            'technicien_sol'   => 'Technicien Sol',
            'agent_social'     => 'Agent Social / ESMS',
            'observateur'      => 'Observateur',
            default            => $role,
        };
    }
}