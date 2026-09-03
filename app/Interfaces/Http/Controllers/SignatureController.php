<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Signature\Application\SignDocument;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tanda Tangan Elektronik — SATU controller generik dipakai lintas
 * jenis dokumen (signable_type), pola SAMA AuditRepository/AuditEntry
 * yang polimorfik murni string. Otorisasi per jenis dokumen ditegakkan
 * DI SINI lewat resolveContext() (match per signable_type) — BUKAN
 * middleware permission tunggal, karena tiap jenis dokumen punya
 * pemilik wewenang tanda tangan yang berbeda-beda.
 *
 * Daftar signable_type yang didukung SENGAJA whitelist eksplisit
 * (match tanpa default selain abort) — mencegah signable_type
 * sembarangan dipakai untuk menandatangani baris tabel yang tidak
 * dimaksudkan sebagai dokumen bertanda tangan.
 */
final class SignatureController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly SignDocument $sign,
    ) {}

    public function store(Request $request, string $signableType, string $signableId): RedirectResponse
    {
        $validated = $request->validate([
            'signature_image_base64' => ['nullable', 'string'],
            'typed_name' => ['nullable', 'string', 'max:150'],
        ]);

        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $context = $this->resolveContext($signableType, $signableId);

        try {
            $this->sign->handle(
                signableType: $signableType,
                signableId: $signableId,
                signerEmployeeId: $actorEmployeeId,
                signerName: $context['signer_name'],
                signerRole: $context['signer_role'],
                signatureImageBase64: $validated['signature_image_base64'] ?? null,
                typedName: $validated['typed_name'] ?? null,
                ipAddress: $request->ip(),
                contextRef: $context['context_ref'],
                actor: new AuditActor(
                    actorId: $actorEmployeeId,
                    actorRole: implode(',', $this->actor->roles()),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect($context['redirect'])->with('sukses', 'Dokumen berhasil ditandatangani secara elektronik.');
    }

    /** @return array{context_ref: string, signer_name: string, signer_role: string, redirect: string} */
    private function resolveContext(string $signableType, string $signableId): array
    {
        return match ($signableType) {
            'decision_letter' => $this->decisionLetterContext($signableId),
            'document_request' => $this->documentRequestContext($signableId),
            default => abort(404, 'Jenis dokumen tidak dikenal.'),
        };
    }

    /** @return array{context_ref: string, signer_name: string, signer_role: string, redirect: string} */
    private function decisionLetterContext(string $id): array
    {
        $letter = DB::table('emp_decision_letters')->where('id', $id)->first();

        abort_if($letter === null, 404);
        abort_unless($this->actor->hasPermission('decision-letter.manage'), 403, 'Anda tidak berwenang menandatangani SK.');

        $signerEmployeeId = $this->actor->employeeId();
        $signerName = $signerEmployeeId !== null
            ? DB::table('emp_employees')->where('id', $signerEmployeeId)->value('full_name')
            : null;

        return [
            'context_ref' => $letter->sk_number,
            'signer_name' => $signerName ?? implode(',', $this->actor->roles()),
            'signer_role' => 'Pejabat SDM',
            'redirect' => route('sk.index'),
        ];
    }

    /** @return array{context_ref: string, signer_name: string, signer_role: string, redirect: string} */
    private function documentRequestContext(string $id): array
    {
        $docRequest = DB::table('doc_requests')->where('id', $id)->first();

        abort_if($docRequest === null, 404);
        abort_unless($docRequest->status === 'siap', 403, 'Dokumen belum diterbitkan — tidak dapat ditandatangani.');
        abort_unless($this->actor->hasPermission('document-request.manage'), 403, 'Anda tidak berwenang menandatangani dokumen ini.');

        $signerEmployeeId = $this->actor->employeeId();
        $signerName = $signerEmployeeId !== null
            ? DB::table('emp_employees')->where('id', $signerEmployeeId)->value('full_name')
            : null;

        return [
            'context_ref' => strtoupper(substr($docRequest->id, 0, 8)),
            'signer_name' => $signerName ?? implode(',', $this->actor->roles()),
            'signer_role' => 'Pejabat SDM',
            'redirect' => route('admin.document-request-queue'),
        ];
    }
}
