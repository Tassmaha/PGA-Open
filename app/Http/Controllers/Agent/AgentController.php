<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Services\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(private readonly AgentService $agentService) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->only([
            'statut', 'sexe', 'distance_profile', 'id_doc_status',
            'geo_unit_ids', 'recherche', 'tri', 'ordre', 'par_page',
        ]);
        $zoneFacilities = $request->attributes->get('zone_facilities');
        $agents = $this->agentService->liste($filtres, $zoneFacilities);

        $agents->getCollection()->transform(fn($a) => [
            'id'                   => $a->id,
            'code'                 => $a->code,
            'nom_complet'          => $a->nom_complet,
            'nom'                  => $a->nom,
            'prenom'               => $a->prenom,
            'sexe'                 => $a->sexe,
            'age'                  => $a->age,
            'telephone'            => $a->telephone,
            'statut'               => $a->statut,
            'distance_profile'     => $a->distance_profile,
            'id_document_number'   => $a->id_document_number,
            'id_document_status'   => $a->id_document_status,
            'id_document_days'     => $a->id_document_days_remaining,
            'id_document_expires'  => $a->id_document_expires_at?->format('d/m/Y'),
            'date_activation'      => $a->date_activation?->format('d/m/Y'),
            'location'             => $a->geoUnit?->name,
            'location_parent'      => $a->geoUnit?->parent?->name,
            'location_path'        => $a->geoUnit?->locationPath(),
        ]);

        return response()->json($agents);
    }

    public function show(Request $request, Agent $agent): JsonResponse
    {
        $agent->load(['geoUnit.level', 'geoUnit.parent.level', 'functionalityStatuses' => fn($q) => $q->latest('period_month')->limit(12), 'paymentStatuses' => fn($q) => $q->latest('period_month')->limit(12)]);
        return response()->json(['agent' => $agent]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'date_naissance' => 'nullable|date',
            'sexe' => 'required|in:M,F',
            'id_document_number' => 'nullable|string|unique:agents,id_document_number',
            'id_document_issued_at' => 'nullable|date',
            'id_document_expires_at' => 'nullable|date|after:id_document_issued_at',
            'telephone' => 'nullable|string|max:20',
            'telephone_alt' => 'nullable|string|max:20',
            'geo_unit_id' => 'required|uuid|exists:geo_units,id',
            'distance_profile' => 'nullable|in:moins_5km,plus_5km',
            'distance_km' => 'nullable|numeric|min:0',
            'date_recrutement' => 'nullable|date',
        ]);

        $agent = $this->agentService->creer($data, $request->user());

        return response()->json([
            'message' => config('pga.agent.type_name') . " {$agent->code} enregistr\u00e9 \u2014 en attente de validation.",
            'agent'   => $agent,
        ], 201);
    }

    public function update(Request $request, Agent $agent): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'date_naissance' => 'nullable|date',
            'sexe' => 'sometimes|in:M,F',
            'id_document_number' => 'nullable|string|unique:agents,id_document_number,' . $agent->id,
            'id_document_issued_at' => 'nullable|date',
            'id_document_expires_at' => 'nullable|date',
            'telephone' => 'nullable|string|max:20',
            'telephone_alt' => 'nullable|string|max:20',
            'geo_unit_id' => 'sometimes|uuid|exists:geo_units,id',
            'distance_profile' => 'nullable|in:moins_5km,plus_5km',
            'distance_km' => 'nullable|numeric|min:0',
        ]);

        $agent = $this->agentService->modifier($agent, $data);
        return response()->json(['message' => 'Informations mises \u00e0 jour.', 'agent' => $agent]);
    }

    public function valider(Request $request, Agent $agent): JsonResponse
    {
        $agent = $this->agentService->valider($agent, $request->user());
        return response()->json(['message' => config('pga.agent.type_name') . " {$agent->code} valid\u00e9.", 'agent' => $agent]);
    }

    public function rejeter(Request $request, Agent $agent): JsonResponse
    {
        $request->validate(['motif' => 'required|string|min:10|max:500']);
        $agent = $this->agentService->rejeter($agent, $request->input('motif'), $request->user());
        return response()->json(['message' => config('pga.agent.type_name') . " {$agent->code} rejet\u00e9.", 'agent' => $agent]);
    }

    public function desactiver(Request $request, Agent $agent): JsonResponse
    {
        $data = $request->validate([
            'motif_desactivation' => 'required|in:abandon_poste,deces,demenagement,non_fonctionnalite_prolongee,retraite,autre',
            'motif_desactivation_detail' => 'nullable|required_if:motif_desactivation,autre|string|max:500',
        ]);
        $agent = $this->agentService->desactiver($agent, $data, $request->user());
        return response()->json(['message' => "{$agent->nom_complet} d\u00e9sactiv\u00e9.", 'agent' => $agent]);
    }

    public function reactiver(Request $request, Agent $agent): JsonResponse
    {
        $agent = $this->agentService->reactiver($agent, $request->user());
        return response()->json(['message' => "{$agent->nom_complet} r\u00e9activ\u00e9.", 'agent' => $agent]);
    }

    public function indicateurs(Request $request): JsonResponse
    {
        $periodes = array_filter((array) $request->input('periodes', []));
        $periode  = !empty($periodes) ? $periodes : $request->input('periode', now()->format('Y-m'));
        $zone     = $request->attributes->get('zone_facilities');
        return response()->json($this->agentService->indicateurs($zone, $periode));
    }
}
