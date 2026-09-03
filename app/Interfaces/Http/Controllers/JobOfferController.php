<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Recruitment\Application\ExtendJobOffer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Membuat tawaran kerja untuk satu lamaran (sisi HC). */
final class JobOfferController extends Controller
{
    public function __construct(private readonly ExtendJobOffer $extend) {}

    public function store(Request $request, string $applicationId): RedirectResponse
    {
        $validated = $request->validate([
            'proposed_position_id' => ['required', 'uuid', 'exists:md_positions,id'],
            'proposed_office_id' => ['required', 'uuid', 'exists:md_offices,id'],
            'proposed_salary_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->extend->handle(
                applicationId: $applicationId,
                proposedPositionId: $validated['proposed_position_id'],
                proposedOfficeId: $validated['proposed_office_id'],
                proposedSalaryNotes: $validated['proposed_salary_notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.recruitment-application-show', $applicationId)->with('sukses', 'Tawaran kerja terkirim.');
    }
}
