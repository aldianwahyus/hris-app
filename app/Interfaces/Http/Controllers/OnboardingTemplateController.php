<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Template checklist Onboarding — HC susun sekali, dipakai berulang
 * lewat GenerateOnboardingChecklist setiap pengajuan pegawai baru
 * disetujui. Tidak ada lingkup kantor (template berlaku bank-wide,
 * dipilih berdasarkan employment_status pegawai, bukan kantornya).
 */
final class OnboardingTemplateController extends Controller
{
    private const CATEGORIES = ['it' => 'IT', 'hc' => 'HC', 'fasilitas' => 'Fasilitas', 'lainnya' => 'Lainnya'];

    private const EMPLOYMENT_STATUSES = ['tetap' => 'Tetap', 'trainee' => 'Trainee', 'kontrak' => 'Kontrak', 'outsource' => 'Outsource'];

    public function index(): View
    {
        $templates = DB::table('onb_checklist_templates')->orderByDesc('created_at')->get();
        $itemCounts = DB::table('onb_checklist_template_items')
            ->select('template_id', DB::raw('count(*) as jumlah'))
            ->groupBy('template_id')
            ->pluck('jumlah', 'template_id');

        return view('admin.onboarding-template-index', ['templates' => $templates, 'itemCounts' => $itemCounts, 'statusLabels' => self::EMPLOYMENT_STATUSES]);
    }

    public function create(): View
    {
        return view('admin.onboarding-template-create', ['categories' => self::CATEGORIES, 'employmentStatuses' => self::EMPLOYMENT_STATUSES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'employment_status_scope' => ['nullable', 'string', Rule::in(array_keys(self::EMPLOYMENT_STATUSES))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:200'],
            'items.*.category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
        ]);

        $now = new DateTimeImmutable;
        $templateId = (string) Uuid7::generate();

        DB::table('onb_checklist_templates')->insert([
            'id' => $templateId,
            'name' => $validated['name'],
            'employment_status_scope' => $validated['employment_status_scope'] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        foreach (array_values($validated['items']) as $order => $item) {
            DB::table('onb_checklist_template_items')->insert([
                'id' => (string) Uuid7::generate(),
                'template_id' => $templateId,
                'item_name' => $item['item_name'],
                'category' => $item['category'],
                'display_order' => $order,
            ]);
        }

        return redirect()->route('admin.onboarding-template-index')->with('sukses', 'Template checklist berhasil dibuat.');
    }

    public function toggleActive(string $id): RedirectResponse
    {
        $template = DB::table('onb_checklist_templates')->where('id', $id)->first();
        abort_if($template === null, 404);

        DB::table('onb_checklist_templates')->where('id', $id)->update([
            'is_active' => ! $template->is_active,
            'updated_at' => new DateTimeImmutable,
            'version' => $template->version + 1,
        ]);

        return redirect()->route('admin.onboarding-template-index')
            ->with('sukses', $template->is_active ? 'Template dinonaktifkan.' : 'Template diaktifkan.');
    }
}
