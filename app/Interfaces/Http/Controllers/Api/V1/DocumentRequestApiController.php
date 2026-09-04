<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Core\Domain\Uuid7;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ESS Mobile (Fase 2) — cermin DocumentRequestController, TIDAK ada
 * Application layer terpisah di jalur web maupun di sini (murni CRUD
 * administratif, pola SAMA jalur web).
 */
final class DocumentRequestApiController
{
    private const DOCUMENT_TYPES = [
        'surat_keterangan_kerja' => 'Surat Keterangan Kerja',
        'surat_referensi' => 'Surat Referensi Kerja',
        'surat_keterangan_penghasilan' => 'Surat Keterangan Penghasilan',
        'lainnya' => 'Lainnya',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $requests = DB::table('doc_requests')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'purpose' => ['required', 'string', 'max:500'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('doc_requests')->insert([
            'id' => $id,
            'employee_id' => $user->employee_id,
            'document_type' => $validated['document_type'],
            'purpose' => $validated['purpose'],
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return response()->json(['id' => $id], 201);
    }

    /** Unduh dokumen yang sudah 'siap' — lingkup SELF, pola SAMA DocumentRequestController::download(). */
    public function download(Request $request, string $id): Response
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $row = DB::table('doc_requests')->where('id', $id)->where('employee_id', $user->employee_id)->first();

        abort_if($row === null, 404);
        abort_unless($row->status === 'siap', 404, 'Dokumen belum diterbitkan.');

        /** @var object{id: string, employee_id: string, document_type: string, purpose: string, processed_at: ?string} $row */
        $employee = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.id', $row->employee_id)
            ->select('e.full_name', 'e.nrp', 'e.join_date', 'o.name as office_name')
            ->first();

        $signature = DB::table('sig_signatures')
            ->where('signable_type', 'document_request')
            ->where('signable_id', $row->id)
            ->orderByDesc('signed_at')
            ->first();

        $pdf = Pdf::loadView('documents.letter-pdf', [
            'row' => $row,
            'employee' => $employee,
            'documentTypeLabel' => self::DOCUMENT_TYPES[$row->document_type] ?? $row->document_type,
            'signature' => $signature,
        ]);

        return $pdf->download('dokumen-'.substr($row->id, 0, 8).'.pdf');
    }
}
