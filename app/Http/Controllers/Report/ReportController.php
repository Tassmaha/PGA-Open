<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\GeoLevel;
use App\Models\GeoUnit;
use App\Services\AgentService;
use App\Services\FunctionalityService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly AgentService         $agentService,
        private readonly FunctionalityService $functService,
        private readonly PaymentService       $paymentService,
    ) {}

    /**
     * GET /api/v1/reports/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $periodes       = array_filter((array) $request->input('periodes', []));
        $periode        = !empty($periodes) ? $periodes : $request->input('periode', now()->format('Y-m'));
        $zoneFacilities = $request->attributes->get('zone_facilities');

        // Filtres multi-r\u00e9gions/districts
        $geoUnitIds = array_filter((array) $request->input('geo_unit_ids', []));
        if (!empty($geoUnitIds)) {
            $facilityLevel = GeoLevel::healthFacilityLevel();
            $filteredFacilities = [];
            foreach ($geoUnitIds as $id) {
                $unit = GeoUnit::find($id);
                if ($unit && $facilityLevel) {
                    $filteredFacilities = array_merge($filteredFacilities,
                        $unit->descendants($facilityLevel->key)->where('status', 'active')->pluck('id')->toArray()
                    );
                }
            }
            $zoneFacilities = $zoneFacilities !== null
                ? array_values(array_intersect($filteredFacilities, $zoneFacilities))
                : $filteredFacilities;
        }

        $asbc      = $this->agentService->indicateurs($zoneFacilities, $periode);
        $statuts   = $this->functService->calculerTaux($periode, $zoneFacilities);
        $paiements = $this->paymentService->calculerTaux($periode, $zoneFacilities);
        $parRegion = $this->indicateursParRegion($periode, $zoneFacilities);
        $evolution = $this->evolution12Mois($zoneFacilities);

        return response()->json([
            'periode'    => $periode,
            'asbc'       => $asbc,
            'statuts'    => $statuts,
            'paiements'  => $paiements,
            'par_region' => $parRegion,
            'evolution'  => $evolution,
        ]);
    }

    /**
     * GET /api/v1/reports/agents
     */
    public function listeAgents(Request $request): JsonResponse
    {
        $zoneFacilities = $request->attributes->get('zone_facilities');
        $statut  = $request->input('statut', 'actif');
        $page    = $request->input('page');
        $parPage = max(1, (int) $request->input('par_page', 30));

        $query = Agent::with(['geoUnit.parent.parent'])
            ->when($statut === 'inactif', fn($q) => $q->inactive())
            ->when($statut !== 'inactif', fn($q) => $q->active())
            ->when($zoneFacilities, function ($q) use ($zoneFacilities) {
                $assignmentIds = GeoUnit::whereIn('parent_id', $zoneFacilities)->active()->pluck('id');
                $q->whereIn('geo_unit_id', $assignmentIds);
            })
            ->orderBy('nom')->orderBy('prenom');

        $mapper = fn($a) => [
            'code'                => $a->code,
            'nom'                 => $a->nom,
            'prenom'              => $a->prenom,
            'sexe'                => $a->sexe,
            'date_naissance'      => $a->date_naissance?->format('d/m/Y'),
            'age'                 => $a->age,
            'telephone'           => $a->telephone,
            'id_document_number'  => $a->id_document_number,
            'id_document_expires' => $a->id_document_expires_at?->format('d/m/Y'),
            'id_document_status'  => $a->id_document_status,
            'distance_profile'    => $a->distance_profile,
            'location'            => $a->geoUnit?->name,
            'facility'            => $a->geoUnit?->parent?->name,
            'date_recrutement'    => $a->date_recrutement?->format('d/m/Y'),
            'date_activation'     => $a->date_activation?->format('d/m/Y'),
        ];

        if ($page !== null) {
            $paginated = $query->paginate($parPage, ['*'], 'page', (int) $page);
            return response()->json([...$paginated->toArray(), 'data' => $paginated->getCollection()->map($mapper)]);
        }

        $data = [];
        foreach ($query->cursor() as $a) { $data[] = $mapper($a); }
        return response()->json(['data' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/v1/reports/id-documents
     */
    public function idDocuments(Request $request): JsonResponse
    {
        $zoneFacilities = $request->attributes->get('zone_facilities');
        $alertDays = config('pga.identity_document.expiration_alert_days', 90);

        $base = Agent::active()->with(['geoUnit.parent'])
            ->when($zoneFacilities, function ($q) use ($zoneFacilities) {
                $assignmentIds = GeoUnit::whereIn('parent_id', $zoneFacilities)->active()->pluck('id');
                $q->whereIn('geo_unit_id', $assignmentIds);
            });

        $mapDoc = fn(string $status) => fn($a) => [
            'code'          => $a->code,
            'nom_complet'   => $a->nom_complet,
            'telephone'     => $a->telephone,
            'doc_number'    => $a->id_document_number,
            'expiration'    => $a->id_document_expires_at?->format('d/m/Y'),
            'days_remaining'=> $a->id_document_days_remaining,
            'facility'      => $a->geoUnit?->parent?->name,
            'doc_status'    => $status,
        ];

        $expired  = (clone $base)->idDocumentExpired()->get()->map($mapDoc('expired'));
        $expiring = (clone $base)->idDocumentExpiringSoon($alertDays)
            ->where('id_document_expires_at', '>=', now())->get()->map($mapDoc('expiring'));

        return response()->json([
            'expired'        => $expired,
            'expiring_soon'  => $expiring,
            'total_expired'  => $expired->count(),
            'total_expiring' => $expiring->count(),
        ]);
    }

    // ── PRIV\u00c9 ──────────────────────────────────────────

    private function indicateursParRegion(string|array $periode, ?array $zoneFacilities): array
    {
        $topLevel = GeoLevel::where('depth', 0)->first();
        if (!$topLevel) return [];

        $regions = GeoUnit::where('geo_level_id', $topLevel->id)->active()->get();
        $facilityLevel = GeoLevel::healthFacilityLevel();

        return $regions->map(function ($region) use ($periode, $zoneFacilities, $facilityLevel) {
            $facilityIds = $region->descendants($facilityLevel?->key ?? 'health_facility')
                ->where('status', 'active')->pluck('id')->toArray();

            if ($zoneFacilities !== null) {
                $facilityIds = array_intersect($facilityIds, $zoneFacilities);
            }
            if (empty($facilityIds)) return null;

            $taux = $this->functService->calculerTaux($periode, $facilityIds);
            return [
                'region'              => $region->name,
                'taux_fonctionnalite' => $taux['taux_fonctionnalite'],
                'taux_saisie'         => $taux['taux_saisie'],
                'total_asbc'          => $taux['total_asbc'],
            ];
        })->filter()->values()->toArray();
    }

    private function evolution12Mois(?array $zoneFacilities): array
    {
        $mois = [];
        for ($i = 11; $i >= 0; $i--) {
            $date    = now()->subMonthsWithoutOverflow($i);
            $periode = $date->format('Y-m');
            $taux    = $this->functService->calculerTaux($periode, $zoneFacilities);
            $pay     = $this->paymentService->calculerTaux($periode, $zoneFacilities);
            $mois[]  = [
                'periode'             => $periode,
                'label'               => $date->translatedFormat('M Y'),
                'taux_fonctionnalite' => $taux['taux_fonctionnalite'],
                'taux_paiement'       => $pay['taux_succes'],
            ];
        }
        return $mois;
    }
}
