<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\GeoLevel;
use App\Models\GeoUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AgentService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    // ── LISTE FILTR\u00c9E ──────────────────────────────────────────

    public function liste(array $filtres, ?array $zoneFacilities): LengthAwarePaginator
    {
        $assignmentLevel = GeoLevel::assignmentLevel();
        $facilityLevel   = GeoLevel::healthFacilityLevel();

        $query = Agent::with(['geoUnit.parent.parent']);

        // Restriction g\u00e9ographique automatique
        if ($zoneFacilities !== null) {
            $assignmentIds = GeoUnit::whereIn('parent_id', $zoneFacilities)
                ->active()->pluck('id')->toArray();
            $query->whereIn('geo_unit_id', $assignmentIds);
        }

        // Filtre statut
        $statut = $filtres['statut'] ?? 'actif';
        match ($statut) {
            'actif'     => $query->active(),
            'inactif'   => $query->inactive(),
            'en_attente'=> $query->pendingValidation(),
            'tous'      => null,
            default     => $query->active(),
        };

        // Filtres g\u00e9ographiques (multi-select ou simple)
        if (!empty($filtres['geo_unit_ids'])) {
            $ids = (array) $filtres['geo_unit_ids'];
            $descendantIds = collect();
            foreach ($ids as $id) {
                $unit = GeoUnit::find($id);
                if ($unit) {
                    $key = $assignmentLevel?->key ?? 'village';
                    $descendantIds = $descendantIds->merge($unit->descendants($key)->pluck('id'));
                }
            }
            if ($descendantIds->isNotEmpty()) {
                $query->whereIn('geo_unit_id', $descendantIds->unique()->toArray());
            }
        }

        // Autres filtres
        if (!empty($filtres['sexe'])) {
            $query->where('sexe', $filtres['sexe']);
        }
        if (!empty($filtres['distance_profile'])) {
            $query->where('distance_profile', $filtres['distance_profile']);
        }
        if (!empty($filtres['id_doc_status'])) {
            match ($filtres['id_doc_status']) {
                'expired'       => $query->idDocumentExpired(),
                'expiring_soon' => $query->idDocumentExpiringSoon(),
                default         => null,
            };
        }

        // Recherche textuelle
        if (!empty($filtres['recherche'])) {
            $terme = $filtres['recherche'];
            $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn($q) => $q
                ->where('nom', $op, "%{$terme}%")
                ->orWhere('prenom', $op, "%{$terme}%")
                ->orWhere('code', $op, "%{$terme}%")
                ->orWhere('id_document_number', $op, "%{$terme}%")
                ->orWhere('telephone', 'like', "%{$terme}%")
            );
        }

        // Tri
        $colonnesTriables = ['nom', 'prenom', 'code', 'date_activation', 'date_naissance', 'telephone', 'statut', 'created_at'];
        $tri = in_array($filtres['tri'] ?? '', $colonnesTriables) ? $filtres['tri'] : 'nom';
        $ordre = in_array(strtolower($filtres['ordre'] ?? ''), ['asc', 'desc']) ? $filtres['ordre'] : 'asc';
        $query->orderBy($tri, $ordre);

        return $query->paginate($filtres['par_page'] ?? 25);
    }

    // ── CR\u00c9ER ──────────────────────────────────────────

    public function creer(array $donnees, User $creePar): Agent
    {
        return DB::transaction(function () use ($donnees, $creePar) {
            $agent = new Agent($donnees);
            $agent->forceFill([
                'code'   => Agent::generateCode(),
                'statut' => 'en_attente_validation',
                'cree_par' => $creePar->id,
            ]);
            $agent->save();

            AuditLog::record('creation', 'agent', $agent->id, $agent->nom_complet, null, $agent->toArray());
            $this->notificationService->notifyValidators($agent);

            return $agent;
        });
    }

    // ── MODIFIER ──────────────────────────────────────────

    public function modifier(Agent $agent, array $donnees): Agent
    {
        return DB::transaction(function () use ($agent, $donnees) {
            $avant = $agent->toArray();
            $agent->update($donnees);

            AuditLog::record('modification', 'agent', $agent->id, $agent->nom_complet, $avant, $agent->fresh()->toArray());

            if (isset($donnees['geo_unit_id'])) {
                cache()->forget("zone_access_{$agent->cree_par}");
            }

            return $agent->fresh();
        });
    }

    // ── VALIDER ──────────────────────────────────────────

    public function valider(Agent $agent, User $validePar): Agent
    {
        if ($agent->statut !== 'en_attente_validation') {
            throw new \LogicException("Cet agent n'est pas en attente de validation.");
        }

        return DB::transaction(function () use ($agent, $validePar) {
            $avant = $agent->toArray();
            $agent->forceFill([
                'statut'          => 'actif',
                'date_activation' => now()->toDateString(),
                'valide_par'      => $validePar->id,
                'valide_le'       => now(),
            ])->save();

            AuditLog::record('validation', 'agent', $agent->id, $agent->nom_complet, $avant, $agent->fresh()->toArray());
            $this->notificationService->notifyValidationResult($agent, 'approved');

            return $agent->fresh();
        });
    }

    // ── REJETER ──────────────────────────────────────────

    public function rejeter(Agent $agent, string $motif, User $rejetePar): Agent
    {
        if ($agent->statut !== 'en_attente_validation') {
            throw new \LogicException("Seul un agent en attente peut \u00eatre rejet\u00e9.");
        }

        return DB::transaction(function () use ($agent, $motif, $rejetePar) {
            $avant = $agent->toArray();
            $agent->forceFill([
                'statut'     => 'rejete',
                'valide_par' => $rejetePar->id,
                'valide_le'  => now(),
            ])->save();

            AuditLog::record('rejection', 'agent', $agent->id, $agent->nom_complet, $avant, $agent->fresh()->toArray());
            $this->notificationService->notifyValidationResult($agent, 'rejected');

            return $agent->fresh();
        });
    }

    // ── R\u00c9ACTIVER ──────────────────────────────────────────

    public function reactiver(Agent $agent, User $reactivePar): Agent
    {
        if (!in_array($agent->statut, ['inactif', 'rejete'])) {
            throw new \LogicException("Seul un agent inactif ou rejet\u00e9 peut \u00eatre r\u00e9activ\u00e9.");
        }

        return DB::transaction(function () use ($agent, $reactivePar) {
            $avant = $agent->toArray();
            $agent->forceFill([
                'statut'                    => 'actif',
                'date_activation'           => now()->toDateString(),
                'date_desactivation'        => null,
                'motif_desactivation'       => null,
                'motif_desactivation_detail' => null,
                'desactive_par'             => null,
                'valide_par'                => $reactivePar->id,
                'valide_le'                 => now(),
            ])->save();

            AuditLog::record('reactivation', 'agent', $agent->id, $agent->nom_complet, $avant, $agent->fresh()->toArray());
            return $agent->fresh();
        });
    }

    // ── D\u00c9SACTIVER ──────────────────────────────────────────

    public function desactiver(Agent $agent, array $donnees, User $desactivePar): Agent
    {
        if ($agent->statut !== 'actif') {
            throw new \LogicException("Seul un agent actif peut \u00eatre d\u00e9sactiv\u00e9.");
        }

        return DB::transaction(function () use ($agent, $donnees, $desactivePar) {
            $avant = $agent->toArray();
            $agent->forceFill([
                'statut'                     => 'inactif',
                'date_desactivation'         => now()->toDateString(),
                'motif_desactivation'        => $donnees['motif_desactivation'],
                'motif_desactivation_detail' => $donnees['motif_desactivation_detail'] ?? null,
                'desactive_par'              => $desactivePar->id,
            ])->save();

            AuditLog::record('deactivation', 'agent', $agent->id, $agent->nom_complet, $avant, $agent->fresh()->toArray());
            return $agent->fresh();
        });
    }

    // ── INDICATEURS ──────────────────────────────────────────

    public function indicateurs(?array $zoneFacilities, string|array $periode): array
    {
        $assignmentLevel = GeoLevel::assignmentLevel();
        $assignmentIds = null;

        if ($zoneFacilities !== null && $assignmentLevel) {
            $assignmentIds = GeoUnit::whereIn('parent_id', $zoneFacilities)
                ->active()->pluck('id')->toArray();
        }

        $baseAgent = Agent::query();
        if ($assignmentIds !== null) {
            $baseAgent->whereIn('geo_unit_id', $assignmentIds);
        }

        $totalActifs   = (clone $baseAgent)->active()->count();
        $totalInactifs = (clone $baseAgent)->inactive()->count();
        $enAttente     = (clone $baseAgent)->pendingValidation()->count();

        // Document d'identit\u00e9
        $idExpires  = (clone $baseAgent)->active()->idDocumentExpired()->count();
        $idExpiring = (clone $baseAgent)->active()->idDocumentExpiringSoon(
            config('pga.identity_document.expiration_alert_days', 90)
        )->count();

        // Fonctionnalit\u00e9
        $periodes = (array) $periode;
        $statutsMois = \App\Models\FunctionalityStatus::whereIn(
            'agent_id', (clone $baseAgent)->active()->pluck('id')
        )->whereIn('period_month', $periodes);

        $fonctionnels = (clone $statutsMois)->functional()->count();
        $tauxFonct    = $totalActifs > 0 ? round(($fonctionnels / $totalActifs) * 100, 1) : 0;

        // Couverture (unit\u00e9s d'affectation ayant 2+ agents actifs)
        $geoQuery = GeoUnit::active();
        if ($assignmentLevel) {
            $geoQuery->where('geo_level_id', $assignmentLevel->id);
        }
        if ($zoneFacilities !== null) {
            $geoQuery->whereIn('parent_id', $zoneFacilities);
        }

        $totalUnits  = (clone $geoQuery)->count();
        $coveredUnits = (clone $geoQuery)->has('activeAgents', '>=', 2)->count();
        $tauxCouverture = $totalUnits > 0 ? round(($coveredUnits / $totalUnits) * 100, 1) : 0;

        // R\u00e9partition villages
        $subQuery = DB::table('geo_units as gu')
            ->leftJoin('agents as a', function ($join) {
                $join->on('a.geo_unit_id', '=', 'gu.id')
                    ->where('a.statut', '=', 'actif')
                    ->whereNull('a.deleted_at');
            })
            ->where('gu.status', 'active')
            ->whereNull('gu.deleted_at');

        if ($assignmentLevel) {
            $subQuery->where('gu.geo_level_id', $assignmentLevel->id);
        }
        if ($zoneFacilities !== null) {
            $subQuery->whereIn('gu.parent_id', $zoneFacilities);
        }

        $subQuery->groupBy('gu.id')->selectRaw('COUNT(a.id) as nb_agents');

        $unitCounts = DB::query()->fromSub($subQuery, 'uc')
            ->selectRaw(implode(', ', [
                'SUM(CASE WHEN nb_agents = 0 THEN 1 ELSE 0 END) as sans_agent',
                'SUM(CASE WHEN nb_agents = 1 THEN 1 ELSE 0 END) as un_agent',
                'SUM(CASE WHEN nb_agents = 2 THEN 1 ELSE 0 END) as deux_agents',
                'SUM(CASE WHEN nb_agents >= 2 THEN 1 ELSE 0 END) as deux_plus_agents',
            ]))->first();

        // Profils
        $feminin  = (clone $baseAgent)->active()->where('sexe', 'F')->count();
        $masculin = (clone $baseAgent)->active()->where('sexe', 'M')->count();
        $moins5km = (clone $baseAgent)->active()->where('distance_profile', 'moins_5km')->count();
        $plus5km  = (clone $baseAgent)->active()->where('distance_profile', 'plus_5km')->count();
        $abandons = (clone $baseAgent)->inactive()->where('motif_desactivation', 'abandon_poste')->count();

        return [
            'total_actifs'           => $totalActifs,
            'total_inactifs'         => $totalInactifs,
            'en_attente_validation'  => $enAttente,
            'id_doc_expired'         => $idExpires,
            'id_doc_expiring_soon'   => $idExpiring,
            'fonctionnels_mois'      => $fonctionnels,
            'taux_fonctionnalite'    => $tauxFonct,
            'total_units'            => $totalUnits,
            'units_covered'          => $coveredUnits,
            'taux_couverture'        => $tauxCouverture,
            'units_sans_agent'       => (int) ($unitCounts->sans_agent ?? 0),
            'units_1_agent'          => (int) ($unitCounts->un_agent ?? 0),
            'units_2_agents'         => (int) ($unitCounts->deux_agents ?? 0),
            'units_2plus_agents'     => (int) ($unitCounts->deux_plus_agents ?? 0),
            'agent_feminin'          => $feminin,
            'agent_masculin'         => $masculin,
            'agent_moins_5km'        => $moins5km,
            'agent_plus_5km'         => $plus5km,
            'abandons'               => $abandons,
            'periode'                => $periode,
        ];
    }
}
