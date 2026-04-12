<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Notifie les validateurs (admin/superviseur) d'un nouvel agent en attente.
     */
    public function notifyValidators(Agent $agent): void
    {
        $validators = User::actif()
            ->whereIn('role', ['admin_dsc', 'superviseur_dsc'])
            ->pluck('id');

        $this->dispatch($validators, 'new_agent_pending', [
            'title'   => config('pga.agent.type_name') . " en attente de validation",
            'message' => "{$agent->nom_complet} ({$agent->code}) \u2014 validation requise.",
            'data'    => ['agent_id' => $agent->id, 'code' => $agent->code],
        ]);
    }

    /**
     * Notifie le cr\u00e9ateur du r\u00e9sultat de validation.
     */
    public function notifyValidationResult(Agent $agent, string $result): void
    {
        if (!$agent->cree_par) return;

        $label = $result === 'approved' ? 'valid\u00e9' : 'rejet\u00e9';
        $this->dispatch(collect([$agent->cree_par]), 'agent_validation', [
            'title'   => config('pga.agent.type_name') . " {$label}",
            'message' => "{$agent->nom_complet} ({$agent->code}) a \u00e9t\u00e9 {$label}.",
            'data'    => ['agent_id' => $agent->id, 'result' => $result],
        ]);
    }

    /**
     * Alerte documents d'identit\u00e9 expirants (appel\u00e9 par scheduler).
     */
    public function alertIdDocumentExpiring(): void
    {
        $threshold = config('pga.identity_document.expiration_alert_days', 90);
        $docName   = config('pga.identity_document.name', 'ID');
        $periode   = now()->format('Y-m');

        $agents = Agent::active()
            ->idDocumentExpiringSoon($threshold)
            ->with('geoUnit.parent')
            ->get();

        // Grouper par facility (parent du village)
        $parFacility = $agents->groupBy(fn($a) => $a->geoUnit?->parent_id);

        foreach ($parFacility as $facilityId => $groupAgents) {
            // Trouver les ICP de cette facility
            $icps = User::actif()
                ->where('role', 'icp')
                ->where('zone_geo_unit_id', $facilityId)
                ->pluck('id');

            if ($icps->isEmpty()) continue;

            // V\u00e9rifier qu'on n'a pas d\u00e9j\u00e0 alert\u00e9 ce mois
            $alreadySent = DB::table('notifications')
                ->whereIn('user_id', $icps)
                ->where('type', 'id_doc_expiring')
                ->where('created_at', '>=', now()->startOfMonth())
                ->exists();

            if ($alreadySent) continue;

            $this->dispatch($icps, 'id_doc_expiring', [
                'title'   => "{$groupAgents->count()} {$docName} expirant(s)",
                'message' => "{$groupAgents->count()} agents ont un {$docName} qui expire dans les {$threshold} jours.",
                'data'    => ['count' => $groupAgents->count(), 'periode' => $periode],
            ]);
        }
    }

    // ── DISPATCH ──────────────────────────────────────────

    private function dispatch($userIds, string $type, array $payload): void
    {
        $now = now();
        $records = $userIds->map(fn($uid) => [
            'id'         => \Illuminate\Support\Str::uuid(),
            'user_id'    => $uid,
            'type'       => $type,
            'title'      => $payload['title'],
            'message'    => $payload['message'] ?? null,
            'data'       => json_encode($payload['data'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        DB::table('notifications')->insert($records);
    }
}
