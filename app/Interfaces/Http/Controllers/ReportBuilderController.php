<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Interfaces\Http\Support\CsvExport;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Modules\Reporting\Application\GenerateReport;
use App\Modules\Reporting\Application\ReportSubjectRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report Builder (Fase 2) — registry subjek (ReportSubjectRegistry),
 * BUKAN drag-and-drop generik bebas (risiko SQL injection dari builder
 * yang benar-benar bebas). Lingkup SAMA PERSIS Rekap Penghasilan:
 * hr_admin kantornya sendiri (OFFICE), hr_approver seluruh bank
 * (BANK_WIDE) — lihat IncomeRecapController.
 */
final class ReportBuilderController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ReportSubjectRegistry $registry,
        private readonly GenerateReport $generate,
    ) {}

    public function index(): View
    {
        return view('admin.report-builder-index', ['subjects' => $this->registry->all()]);
    }

    public function show(string $subjectKey): View
    {
        $subject = $this->registry->find($subjectKey);
        abort_if($subject === null, 404);

        return view('admin.report-builder-show', ['subject' => $subject]);
    }

    public function download(Request $request, string $subjectKey): StreamedResponse|Response|RedirectResponse
    {
        $subject = $this->registry->find($subjectKey);
        abort_if($subject === null, 404);

        $columnKeys = $request->query('columns', []);
        $format = $request->string('format')->toString();
        $filters = [
            'start' => $request->string('start')->toString(),
            'end' => $request->string('end')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        try {
            [$columns, $rows] = $this->generate->handle($subjectKey, is_array($columnKeys) ? $columnKeys : [], $filters, $this->scopedOfficeId());
        } catch (DomainException $e) {
            return redirect()->route('hr.report-builder.show', $subjectKey)->with('gagal', $e->getMessage());
        }

        if ($format === 'pdf') {
            return Pdf::loadView('admin.report-builder-pdf', [
                'subject' => $subject,
                'columns' => $columns,
                'rows' => $rows,
            ])->stream("laporan-{$subjectKey}.pdf");
        }

        $headers = array_map(fn ($c) => $c->label, $columns);
        $csvRows = $rows->map(fn ($row) => array_map(fn ($c) => (string) ($row->{$c->key} ?? ''), $columns))->all();

        return CsvExport::download("laporan-{$subjectKey}.csv", $headers, $csvRows);
    }

    /** null = bank-wide (hr_approver); string = lingkup kantor sendiri (hr_admin). */
    private function scopedOfficeId(): ?string
    {
        if (! $this->actor->hasRole(Role::HrAdmin->value)) {
            return null;
        }

        $officeId = $this->actor->officeId();
        abort_if($officeId === null, 403, 'Akun ini belum ditautkan ke kantor mana pun.');

        return $officeId;
    }
}
