<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\FunctionalityStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FunctionalityService
{
    /**
     * Saisir ou mettre \u00e0 jour le statut de fonctionnalit\u00e9 d'un agent pour une p\u00e9riode.
     */
    public function saisirOuMettreAJour(Agent $agent, string $periode, array $donnees, User $saisiPar): FunctionalityStatus
    {
        return DB::transaction(function () use ($agent, $periode, $donnees, $saisiPar) {
            $statut = FunctionalityStatus::updateOrCreate(
                ['agent_id' => $agent->id, 'period_month' => $periode],
                [
                    'crit_presence'   => $donnees['crit_presence'] ?? false,
                    'crit_knowledge'  => $donnees['crit_knowledge'] ?? false,
                    'crit_stock'      => $donnees['crit_stock'] ?? false,
                    'crit_community'  => $donnees['crit_community'] ?? false,
                    'entered_by'      => $saisiPar->id,
                    'entered_at'      => now(),
                ]
            );

            // Calcul automatique du statut global
            $allTrue = $statut->crit_presence && $statut->crit_knowledge
                    && $statut->crit_stock && $statut->crit_community;
            $statut->status_global = $allTrue ? 'functional' : 'non_functional';
            $statut->save();

            AuditLog::record('functionality_entry', 'agent', $agent->id, $agent->nom_complet, null, $statut->toArray());

            return $statut;
        });
    }

    /**
     * Validation RPS.
     */
    public function validerRps(FunctionalityStatus $statut, User $validePar): FunctionalityStatus
    {
        $statut->update([
            'validation_status'          => 'validated_supervisor',
            'validated_by_supervisor'     => $validePar->id,
            'validated_supervisor_at'     => now(),
        ]);

        AuditLog::record('functionality_validation', 'agent', $statut->agent_id, null, null, $statut->toArray());
        return $statut->fresh();
    }

    /**
     * Calcul des taux de fonctionnalit\u00e9 pour une p\u00e9riode et zone.
     */
    public function calculerTaux(string|array $periode, ?array $zoneFacilities): array
    {
        $agentQuery = Agent::active();
        if ($zoneFacilities !== null) {
            $assignmentIds = \App\Models\GeoUnit::whereIn('parent_id', $zoneFacilities)
                ->active()->pluck('id')->toArray();
            $agentQuery->whereIn('geo_unit_id', $assignmentIds);
        }

        $agentIds  = $agentQuery->pluck('id');
        $totalAsbc = $agentIds->count();

        if ($totalAsbc === 0) {
            return $this->tauxVides($periode);
        }

        $periodes = (array) $periode;
        $statuts = FunctionalityStatus::whereIn('agent_id', $agentIds)
            ->whereIn('period_month', $periodes)
            ->get();

        $statutsParAgent = $statuts->groupBy('agent_id');
        $nbFonctionnels  = $statutsParAgent->filter(fn($g) => $g->contains('status_global', 'functional'))->count();
        $nbNonFonct      = $statutsParAgent->filter(fn($g) => !$g->contains('status_global', 'functional') && $g->contains('status_global', 'non_functional'))->count();
        $nbNonSaisis     = $totalAsbc - $statutsParAgent->count();

        // Promptitude
        $dernierePeriode = collect($periodes)->sort()->last();
        $dateButee = \Carbon\Carbon::createFromFormat('Y-m', $dernierePeriode)->addMonth()->day(5)->endOfDay();
        $nbPromptitude = $statuts->where('status_global', 'functional')
            ->where('validation_status', '!=', 'draft')
            ->filter(fn($s) => $s->validated_supervisor_at && $s->validated_supervisor_at <= $dateButee)
            ->unique('agent_id')
            ->count();

        $tauxSaisie = $totalAsbc > 0 ? round(($statutsParAgent->count() / $totalAsbc) * 100, 1) : 0;

        return [
            'periode'             => $periode,
            'total_asbc'          => $totalAsbc,
            'nb_fonctionnels'     => $nbFonctionnels,
            'nb_non_fonctionnels' => $nbNonFonct,
            'nb_non_saisis'       => $nbNonSaisis,
            'taux_fonctionnalite' => $totalAsbc > 0 ? round(($nbFonctionnels / $totalAsbc) * 100, 1) : 0,
            'nb_promptitude'      => $nbPromptitude,
            'taux_promptitude'    => $totalAsbc > 0 ? round(($nbPromptitude / $totalAsbc) * 100, 1) : 0,
            'taux_saisie'         => $tauxSaisie,
        ];
    }

    private function tauxVides(string|array $periode): array
    {
        return [
            'periode' => $periode, 'total_asbc' => 0,
            'nb_fonctionnels' => 0, 'nb_non_fonctionnels' => 0, 'nb_non_saisis' => 0,
            'taux_fonctionnalite' => 0, 'nb_promptitude' => 0, 'taux_promptitude' => 0,
            'taux_saisie' => 0,
        ];
    }
}
