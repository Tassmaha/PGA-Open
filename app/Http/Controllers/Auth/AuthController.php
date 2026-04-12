<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->where('actif', true)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        $user->update(['derniere_connexion' => now()]);
        $token = $user->createToken('pga-session')->plainTextToken;

        AuditLog::record('login', 'user', $user->id, $user->nom_complet);

        return response()->json([
            'token'       => $token,
            'utilisateur' => [
                'id'     => $user->id,
                'nom'    => $user->nom_complet,
                'email'  => $user->email,
                'role'   => $user->role,
                'zone'   => $user->zone_geo_unit_id,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'D\u00e9connect\u00e9.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('zoneGeoUnit.level');

        return response()->json([
            'id'           => $user->id,
            'nom'          => $user->nom_complet,
            'prenom'       => $user->prenom,
            'email'        => $user->email,
            'telephone'    => $user->telephone,
            'role'         => $user->role,
            'role_label'   => config("pga.roles.{$user->role}", $user->role),
            'zone'         => $user->zoneGeoUnit ? [
                'id'    => $user->zoneGeoUnit->id,
                'name'  => $user->zoneGeoUnit->name,
                'level' => $user->zoneGeoUnit->level?->name,
            ] : null,
            'actif'              => $user->actif,
            'derniere_connexion' => $user->derniere_connexion?->toIso8601String(),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        AuditLog::record('password_change', 'user', $user->id, $user->nom_complet);

        return response()->json(['message' => 'Mot de passe modifi\u00e9 avec succ\u00e8s.']);
    }
}
