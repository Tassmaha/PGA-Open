<?php

/**
 * PGA Open — Configuration centrale.
 *
 * Chaque cl\u00e9 peut \u00eatre surcharg\u00e9e par le tenant via la colonne `config` JSON
 * de la table `tenants`. Le middleware TenantResolver fusionne automatiquement
 * les valeurs tenant dans ce namespace au boot de chaque requ\u00eate.
 */

return [

    // ── Pays ────────────────────────────────────────────────────────
    'country' => [
        'name'               => env('PGA_COUNTRY', 'Burkina Faso'),
        'code'               => env('PGA_COUNTRY_CODE', 'BF'),
        'currency'           => env('PGA_CURRENCY', 'FCFA'),
        'currency_symbol'    => env('PGA_CURRENCY_SYMBOL', 'FCFA'),
        'phone_country_code' => env('PGA_PHONE_CODE', '+226'),
        'locale'             => env('APP_LOCALE', 'fr'),
        'timezone'           => env('APP_TIMEZONE', 'Africa/Ouagadougou'),
    ],

    // ── Agent de sant\u00e9 communautaire ──────────────────────────────────
    'agent' => [
        'type_name'      => 'ASBC',
        'type_name_full' => 'Agent de Sant\u00e9 \u00e0 Base Communautaire',
        'code_prefix'    => 'ASBC',
        'age_minimum'    => 18,
        'age_maximum'    => 65,
        'delai_validation_jours' => 5,
    ],

    // ── Document d'identit\u00e9 ────────────────────────────────────────
    'identity_document' => [
        'name'                  => 'CNIB',
        'name_full'             => 'Carte Nationale d\'Identit\u00e9 Burkinab\u00e8',
        'length_min'            => 9,
        'length_max'            => 11,
        'expiration_alert_days' => 90,
        'reminder_frequency_days' => 30,
    ],

    // ── Formation sanitaire / \u00e9tablissement de sant\u00e9 ───────────────
    'health_facility' => [
        'type_name' => 'CSPS',
        'types'     => ['CSPS', 'CMA', 'CHR', 'autre'],
    ],

    // ── Paiement mobile ──────────────────────────────────────────────
    'payment' => [
        'provider_name'   => 'Orange Money',
        'success_statuses'  => ['succeeded', 'success'],
        'refund_statuses'   => ['refunded'],
        'column_mapping'  => [
            'telephone' => ['mobile number*', 'mobile number', 'telephone', 'msisdn', 'numero', 'phone'],
            'montant'   => ['amount*', 'amount', 'montant', 'sum'],
            'statut'    => ['servicerequeststatus', 'service_request_status', 'statut_orange', 'status', 'statut'],
        ],
        'formats_acceptes'  => ['csv', 'xlsx', 'xls'],
        'taille_max_mo'     => 10,
    ],

    // ── Validation ──────────────────────────────────────────────────
    'validation' => [
        'phone_regex'    => '/^\d{8}$/',
        'phone_prefixes' => ['05', '06', '07'],
    ],

    // ── Saisie fonctionnalit\u00e9 (calendrier) ──────────────────────────
    'saisie' => [
        'ouverture_jour'       => 26,
        'fermeture_icp_jour'   => 5,
        'fermeture_rps_jour'   => 10,
        'verrouillage_jour'    => 15,
    ],

    // ── Indicateurs ─────────────────────────────────────────────────
    'indicateurs' => [
        'fonctionnalite_seuil_alerte' => 70,
        'impaye_seuil_alerte'         => 10,
    ],

    // ── Organisation / minist\u00e8re ────────────────────────────────────
    'organization' => [
        'ministry'    => 'Minist\u00e8re de la Sant\u00e9 et de l\'Hygi\u00e8ne Publique',
        'department'  => 'Direction de la Sant\u00e9 Communautaire',
        'admin_domain' => 'dsc.gov.bf',
        'support_email' => 'support@dsc.gov.bf',
    ],

    // ── Branding ────────────────────────────────────────────────────
    'branding' => [
        'app_name'       => 'PGA Open',
        'logo'           => 'branding/logo.webp',
        'coat_of_arms'   => 'branding/armoirie.webp',
        'primary_color'  => '#1B9E5A',
        'login_background' => 'branding/background.jpg',
        'geojson'        => 'branding/map.geojson',
    ],

    // ── R\u00f4les utilisateurs ─────────────────────────────────────────
    'roles' => [
        'admin_dsc'       => 'Admin DSC',
        'superviseur_dsc' => 'Superviseur DSC',
        'drs'             => 'Directeur R\u00e9gional de Sant\u00e9',
        'rps'             => 'Responsable Point de Sant\u00e9',
        'icp'             => 'Infirmier Chef de Poste',
        'ptf'             => 'Partenaire Technique et Financier',
    ],
];
