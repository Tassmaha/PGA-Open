<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\PaymentImport;
use App\Models\PaymentStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class PaymentService
{
    /**
     * Importe un fichier de paiement mobile money.
     */
    public function importer(string $filePath, string $fileName, string $periode, User $importePar): PaymentImport
    {
        return DB::transaction(function () use ($filePath, $fileName, $periode, $importePar) {
            $import = PaymentImport::create([
                'period_month' => $periode,
                'file_name'    => $fileName,
                'file_path'    => $filePath,
                'status'       => 'processing',
                'imported_by'  => $importePar->id,
            ]);

            $rows = $this->parserFichier($filePath, $fileName);
            $stats = $this->traiterLignes($rows, $import, $periode);

            $import->update([
                'status'          => 'completed',
                'total_rows'      => $stats['total'],
                'success_count'   => $stats['success'],
                'failure_count'   => $stats['failure'],
                'refund_count'    => $stats['refund'],
                'not_found_count' => $stats['not_found'],
                'total_amount'    => $stats['amount'],
                'success_rate'    => $stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 1) : 0,
            ]);

            AuditLog::record('payment_import', 'payment_import', $import->id, "Import {$periode} \u2014 {$fileName}");

            return $import->fresh();
        });
    }

    /**
     * Cl\u00f4turer un import (rend non modifiable).
     */
    public function cloturer(PaymentImport $import): PaymentImport
    {
        $import->update(['closed' => true, 'closed_at' => now()]);
        AuditLog::record('payment_close', 'payment_import', $import->id, "Cl\u00f4ture import {$import->period_month}");
        return $import->fresh();
    }

    /**
     * Annuler un import (supprime les statuts associ\u00e9s).
     */
    public function annuler(PaymentImport $import): void
    {
        DB::transaction(function () use ($import) {
            $import->paymentStatuses()->delete();
            if ($import->file_path && Storage::exists($import->file_path)) {
                Storage::delete($import->file_path);
            }
            AuditLog::record('payment_cancel', 'payment_import', $import->id, "Annulation import {$import->period_month}");
            $import->delete();
        });
    }

    /**
     * Calcul des taux de paiement pour une p\u00e9riode.
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
            return ['periode' => $periode, 'total_asbc' => 0, 'nb_payes' => 0, 'nb_echecs' => 0,
                    'nb_sans_paiement' => 0, 'montant_total' => 0, 'taux_succes' => 0, 'taux_echec' => 0];
        }

        $paiements = PaymentStatus::whereIn('agent_id', $agentIds)
            ->whereIn('period_month', (array) $periode)->get();

        $reussis = $paiements->where('status', 'success')->count();
        $echecs  = $paiements->where('status', 'failure')->count();
        $montant = $paiements->where('status', 'success')->sum('amount');

        return [
            'periode'      => $periode,
            'total_asbc'   => $totalAsbc,
            'nb_payes'     => $reussis,
            'nb_echecs'    => $echecs,
            'nb_sans_paiement' => $totalAsbc - $reussis - $echecs,
            'montant_total'    => $montant,
            'taux_succes'  => $totalAsbc > 0 ? round(($reussis / $totalAsbc) * 100, 1) : 0,
            'taux_echec'   => $totalAsbc > 0 ? round(($echecs / $totalAsbc) * 100, 1) : 0,
        ];
    }

    // ── PARSING ──────────────────────────────────────────

    private function parserFichier(string $path, string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fullPath = Storage::path($path);

        if ($ext === 'csv') {
            return $this->parserCsv($fullPath);
        }
        return $this->parserExcel($fullPath);
    }

    private function parserCsv(string $path): array
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $headers = array_map(fn($h) => $this->normaliserHeader($h), $csv->getHeader());
        $rows = [];

        foreach ($csv->getRecords() as $record) {
            $mapped = [];
            foreach ($headers as $i => $key) {
                $mapped[$key] = array_values($record)[$i] ?? '';
            }
            $rows[] = $mapped;
        }
        return $rows;
    }

    private function parserExcel(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, true);

        // Trouver la ligne d'en-t\u00eate
        $headerRow = null;
        foreach ($data as $i => $row) {
            $nonEmpty = array_filter($row, fn($v) => $v !== null && $v !== '');
            if (count($nonEmpty) >= 3) { $headerRow = $i; break; }
        }
        if (!$headerRow) return [];

        $headers = array_map(fn($h) => $this->normaliserHeader((string) $h), $data[$headerRow]);
        $rows = [];
        foreach ($data as $i => $row) {
            if ($i <= $headerRow) continue;
            $mapped = [];
            foreach ($headers as $col => $key) {
                $mapped[$key] = $row[$col] ?? '';
            }
            $rows[] = $mapped;
        }
        return $rows;
    }

    private function normaliserHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $mapping = config('pga.payment.column_mapping', []);

        foreach ($mapping as $internalKey => $variants) {
            if (in_array($h, $variants)) return $internalKey;
        }
        return $h;
    }

    private function traiterLignes(array $rows, PaymentImport $import, string $periode): array
    {
        $successStatuses = config('pga.payment.success_statuses', ['succeeded', 'success']);
        $refundStatuses  = config('pga.payment.refund_statuses', ['refunded']);

        // Charger tous les agents actifs par t\u00e9l\u00e9phone (bulk lookup)
        $agentsParTel = Agent::active()
            ->whereNotNull('telephone')->where('telephone', '!=', '')
            ->pluck('id', 'telephone')->toArray();

        $stats = ['total' => 0, 'success' => 0, 'failure' => 0, 'refund' => 0, 'not_found' => 0, 'amount' => 0];
        $seen = [];

        foreach ($rows as $row) {
            $tel = preg_replace('/\D/', '', $row['telephone'] ?? '');
            if ($tel === '' || isset($seen[$tel])) continue;
            $seen[$tel] = true;
            $stats['total']++;

            $rawStatus = mb_strtolower(trim($row['statut'] ?? ''));
            $montant   = (float) preg_replace('/[^\d.]/', '', $row['montant'] ?? '0');

            if (in_array($rawStatus, $successStatuses)) {
                $status = 'success';
                $stats['success']++;
                $stats['amount'] += $montant;
            } elseif (in_array($rawStatus, $refundStatuses)) {
                $status = 'refunded';
                $stats['refund']++;
            } else {
                $status = 'failure';
                $stats['failure']++;
            }

            $agentId = $agentsParTel[$tel] ?? null;
            if (!$agentId) $stats['not_found']++;

            PaymentStatus::create([
                'agent_id'          => $agentId,
                'payment_import_id' => $import->id,
                'period_month'      => $periode,
                'phone_number'      => $tel,
                'amount'            => $montant,
                'status'            => $status,
                'raw_status'        => $rawStatus,
            ]);
        }

        return $stats;
    }
}
