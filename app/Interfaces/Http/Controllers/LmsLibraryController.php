<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Digital Library (BRD §5.7) — ESS, semua pegawai boleh menjelajah
 * TANPA middleware permission (pola sama LmsEnrollmentController).
 * open() mencatat baris lms_library_access_logs SEBELUM
 * redirect/unduh — ini fungsi "tracking aktivitas" BRD.
 */
final class LmsLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $query = DB::table('lms_library_items as li')
            ->leftJoin('lms_courses as c', 'c.id', '=', 'li.course_id')
            ->where('li.is_active', true)
            ->select('li.*', 'c.title as course_title');

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $query->where(fn ($q) => $q->where('li.title', 'like', "%{$keyword}%")
                ->orWhere('li.description', 'like', "%{$keyword}%"));
        }

        $category = trim((string) $request->query('kategori', ''));
        if ($category !== '') {
            $query->where('li.category', $category);
        }

        $items = $query->orderBy('li.title')->get();

        $categories = DB::table('lms_library_items')
            ->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('lms.library-index', compact('items', 'categories', 'keyword', 'category'));
    }

    public function open(Request $request, string $id): RedirectResponse|StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $item = DB::table('lms_library_items')->where('id', $id)->where('is_active', true)->first();
        abort_if($item === null, 404);

        DB::table('lms_library_access_logs')->insert([
            'id' => (string) Uuid7::generate(),
            'library_item_id' => $id,
            'employee_id' => $user->employee_id,
            'accessed_at' => new DateTimeImmutable,
            'created_at' => new DateTimeImmutable,
        ]);

        if ($item->external_url !== null) {
            return redirect()->away($item->external_url);
        }

        abort_if($item->file_path === null, 404);

        return Storage::disk('s3')->download($item->file_path, $item->file_original_name);
    }
}
