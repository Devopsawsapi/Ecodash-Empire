<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

// ════════════════════════════════════════════════════════════════
// UserController — Gestion des utilisateurs (Admin uniquement)
// CRUD complet + activation/désactivation + réinitialisation mot de passe
// ════════════════════════════════════════════════════════════════

class UserController extends Controller
{
    // ─── LISTE DES UTILISATEURS ──────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $query = User::query();

        // Filtres
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($qb) => $qb->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('matricule', 'like', "%$q%"));
        }

        $users = $query->orderBy('name')->get()->map(fn($u) => $this->formatUser($u));

        // Statistiques
        $stats = [
            'total'     => User::count(),
            'actifs'    => User::where('actif', true)->count(),
            'admins'    => User::where('role', 'admin')->count(),
            'ingenieurs'=> User::whereIn('role', ['ingenieur_env', 'ingenieur_social'])->count(),
            'techniciens'=> User::whereIn('role', ['technicien_eau', 'technicien_air', 'technicien_sol', 'agent_social', 'observateur'])->count(),
        ];

        return response()->json([
            'data'  => $users,
            'stats' => $stats,
        ]);
    }

    // ─── CRÉER UN UTILISATEUR ────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'email'            => 'required|email|unique:users,email',
            'password'         => ['required', Password::min(8)],
            'telephone'        => 'nullable|string|max:20',
            'matricule'        => 'nullable|string|max:30|unique:users,matricule',
            'role'             => ['required', Rule::in(['admin', 'ingenieur_env', 'ingenieur_social', 'technicien_eau', 'technicien_air', 'technicien_sol', 'agent_social', 'observateur'])],
            'modules_acces'    => 'nullable|array',
            'modules_acces.*'  => Rule::in(['eau', 'air', 'sol', 'social', 'biodiversite']),
            'zone_affectation' => 'nullable|string|max:100',
            'actif'            => 'boolean',
        ]);

        // Modules par défaut selon le rôle
        if (empty($validated['modules_acces'])) {
            $validated['modules_acces'] = $this->defaultModules($validated['role']);
        }

        // Auto-matricule si non fourni
        if (empty($validated['matricule'])) {
            $validated['matricule'] = 'ECO-' . strtoupper(substr($validated['role'], 0, 3)) . '-' . str_pad(User::count() + 1, 4, '0', STR_PAD_LEFT);
        }

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'actif'    => $validated['actif'] ?? true,
        ]);

        return response()->json([
            'message' => 'Utilisateur créé avec succès.',
            'data'    => $this->formatUser($user),
        ], 201);
    }

    // ─── AFFICHER UN UTILISATEUR ─────────────────────────────────
    public function show(Request $request, User $user): JsonResponse
    {
        $this->requireAdmin($request);

        return response()->json(['data' => $this->formatUser($user)]);
    }

    // ─── METTRE À JOUR UN UTILISATEUR ───────────────────────────
    public function update(Request $request, User $user): JsonResponse
    {
        $this->requireAdmin($request);

        // Empêcher la modification de l'admin principal via API
        if ($user->id === 1 && $request->user()->id !== 1) {
            return response()->json(['message' => 'Impossible de modifier le compte administrateur principal.'], 403);
        }

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'email'            => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password'         => ['sometimes', Password::min(8)],
            'telephone'        => 'nullable|string|max:20',
            'matricule'        => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user->id)],
            'role'             => ['sometimes', Rule::in(['admin', 'ingenieur_env', 'ingenieur_social', 'technicien_eau', 'technicien_air', 'technicien_sol', 'agent_social', 'observateur'])],
            'modules_acces'    => 'nullable|array',
            'modules_acces.*'  => Rule::in(['eau', 'air', 'sol', 'social', 'biodiversite']),
            'zone_affectation' => 'nullable|string|max:100',
            'actif'            => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            // Invalider les tokens si le mot de passe change
            $user->tokens()->delete();
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'data'    => $this->formatUser($user->fresh()),
        ]);
    }

    // ─── SUPPRIMER UN UTILISATEUR ────────────────────────────────
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->requireAdmin($request);

        if ($user->id === 1) {
            return response()->json(['message' => 'Impossible de supprimer le compte administrateur principal.'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }

        // Révoquer tous les tokens
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    // ─── ACTIVER / DÉSACTIVER ────────────────────────────────────
    public function toggleActif(Request $request, User $user): JsonResponse
    {
        $this->requireAdmin($request);

        if ($user->id === 1) {
            return response()->json(['message' => 'Impossible de désactiver le compte administrateur principal.'], 403);
        }

        $user->update(['actif' => ! $user->actif]);

        if (! $user->actif) {
            // Révoquer les tokens si désactivé
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->actif ? 'Compte activé.' : 'Compte désactivé.',
            'actif'   => $user->actif,
        ]);
    }

    // ─── RÉINITIALISER LE MOT DE PASSE (Admin) ──────────────────
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->requireAdmin($request);

        $request->validate([
            'password' => ['required', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Mot de passe réinitialisé. L\'utilisateur devra se reconnecter.']);
    }

    // ─── HELPERS ─────────────────────────────────────────────────
    private function requireAdmin(Request $request): void
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Accès réservé aux administrateurs.');
        }
    }

    private function defaultModules(string $role): array
    {
        return match ($role) {
            'admin'            => ['eau', 'air', 'sol', 'social', 'biodiversite'],
            'ingenieur_env'    => ['eau', 'air', 'sol'],
            'ingenieur_social' => ['social', 'biodiversite'],
            'technicien_eau'   => ['eau'],
            'technicien_air'   => ['air'],
            'technicien_sol'   => ['sol'],
            'agent_social'     => ['social'],
            'observateur'      => ['biodiversite'],
            default            => [],
        };
    }

    private function formatUser(User $user): array
    {
        return [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'telephone'        => $user->telephone,
            'matricule'        => $user->matricule,
            'role'             => $user->role,
            'modules_acces'    => $user->modules_acces ?? [],
            'zone_affectation' => $user->zone_affectation,
            'photo'            => $user->photo,
            'actif'            => $user->actif,
            'created_at'       => $user->created_at?->format('d/m/Y'),
        ];
    }
}