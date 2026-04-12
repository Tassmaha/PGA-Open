<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserCredentials;
use App\Mail\PasswordReset;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::withTrashed()
            ->with('zoneGeoUnit:id,name')
            ->orderBy('nom')
            ->paginate(50);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'email'            => 'required|email|unique:users,email',
            'telephone'        => 'nullable|string|max:20',
            'role'             => 'required|string',
            'zone_geo_unit_id' => 'nullable|uuid|exists:geo_units,id',
        ]);

        $mdpTemp = Str::random(6) . rand(10, 99);

        $user = User::create([
            ...$data,
            'password' => Hash::make($mdpTemp),
            'actif'    => true,
        ]);

        AuditLog::record('creation', 'user', $user->id, $user->nom_complet);

        $emailEnvoye = false;
        try {
            $urlConnexion = rtrim(config('app.url'), '/') . '/pages/login.html';
            Mail::to($user->email)->send(new UserCredentials($user, $mdpTemp, $urlConnexion));
            $emailEnvoye = true;
        } catch (\Throwable $e) {
            Log::warning("Impossible d'envoyer l'email \u00e0 {$user->email}: {$e->getMessage()}");
        }

        return response()->json([
            'message'      => $emailEnvoye
                ? "Utilisateur cr\u00e9\u00e9. Identifiants envoy\u00e9s par email."
                : "Utilisateur cr\u00e9\u00e9. L'email a \u00e9chou\u00e9 \u2014 transmettez le mot de passe manuellement.",
            'utilisateur'  => $user,
            'mdp_temp'     => $mdpTemp,
            'email_envoye' => $emailEnvoye,
        ], 201);
    }

    public function update(Request $request, User $utilisateur): JsonResponse
    {
        $data = $request->validate([
            'nom'              => 'sometimes|string|max:100',
            'prenom'           => 'sometimes|string|max:100',
            'email'            => 'sometimes|email|unique:users,email,' . $utilisateur->id,
            'telephone'        => 'nullable|string|max:20',
            'role'             => 'sometimes|string',
            'zone_geo_unit_id' => 'nullable|uuid|exists:geo_units,id',
            'actif'            => 'sometimes|boolean',
        ]);

        $avant = $utilisateur->toArray();
        $utilisateur->update($data);

        AuditLog::record('modification', 'user', $utilisateur->id, $utilisateur->nom_complet, $avant, $utilisateur->fresh()->toArray());
        cache()->forget("zone_access_{$utilisateur->id}");

        return response()->json(['message' => 'Utilisateur mis \u00e0 jour.', 'utilisateur' => $utilisateur->fresh()]);
    }

    public function show(User $utilisateur): JsonResponse
    {
        return response()->json($utilisateur->load('zoneGeoUnit'));
    }

    public function destroy(User $utilisateur): JsonResponse
    {
        $utilisateur->tokens()->delete();
        $utilisateur->delete();
        cache()->forget("zone_access_{$utilisateur->id}");
        AuditLog::record('deactivation', 'user', $utilisateur->id, $utilisateur->nom_complet);

        return response()->json(['message' => 'Utilisateur d\u00e9sactiv\u00e9.']);
    }

    public function reinitialiserMotDePasse(User $utilisateur): JsonResponse
    {
        $mdpTemp = Str::random(6) . rand(10, 99);
        $utilisateur->update(['password' => Hash::make($mdpTemp)]);
        $utilisateur->tokens()->delete();

        AuditLog::record('password_reset', 'user', $utilisateur->id, "R\u00e9init MDP \u2014 {$utilisateur->nom_complet}");

        $emailEnvoye = false;
        try {
            $urlConnexion = rtrim(config('app.url'), '/') . '/pages/login.html';
            Mail::to($utilisateur->email)->send(new PasswordReset($utilisateur, $mdpTemp, $urlConnexion));
            $emailEnvoye = true;
        } catch (\Throwable $e) {
            Log::warning("Impossible d'envoyer l'email de r\u00e9init MDP \u00e0 {$utilisateur->email}: {$e->getMessage()}");
        }

        return response()->json([
            'message'      => $emailEnvoye
                ? "Mot de passe r\u00e9initialis\u00e9. Envoy\u00e9 par email."
                : "Mot de passe r\u00e9initialis\u00e9. L'email a \u00e9chou\u00e9 \u2014 transmettez-le manuellement.",
            'mdp_temp'     => $mdpTemp,
            'email_envoye' => $emailEnvoye,
        ]);
    }
}
