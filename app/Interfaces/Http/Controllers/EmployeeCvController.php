<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Employee\Application\UpdateOwnPersonalDetails;
use App\Modules\Employee\Domain\ProfileChangeConflict;
use App\Modules\Employee\Domain\SelfEditableEmployeeField;
use App\Shared\Audit\Domain\AuditActor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "CV Saya" (ESS, lingkup SELF) — data organisasi (jabatan/kantor/
 * grade/dll) ditampilkan HANYA-BACA di sini; data pribadi
 * (SelfEditableEmployeeField) bisa diubah LANGSUNG tanpa persetujuan.
 */
final class EmployeeCvController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly UpdateOwnPersonalDetails $update,
    ) {}

    public function show(): View
    {
        return view('ess.cv', $this->assembleCvData());
    }

    public function pdf(): Response
    {
        $data = $this->assembleCvData();

        $pdf = Pdf::loadView('ess.cv-pdf', $data);

        return $pdf->download("cv-{$data['employee']->nrp}.pdf");
    }

    public function update(Request $request): RedirectResponse
    {
        $employee = $this->ownEmployee();
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'alamat' => ['nullable', 'string', 'max:2000'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'kontak_darurat_nama' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['nullable', 'string', 'max:50'],
            'kontak_darurat_telepon' => ['nullable', 'string', 'max:20'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:30'],
            'pendidikan_jurusan' => ['nullable', 'string', 'max:100'],
        ]);

        $changes = [];

        foreach (SelfEditableEmployeeField::values() as $field) {
            // photo_path diubah lewat updatePhoto()/removePhoto() (rute
            // & form TERPISAH, unggah berkas) — tidak pernah ada di
            // $validated milik form teks ini. Tanpa pengecekan ini,
            // photo_path akan dianggap "berubah jadi null" setiap kali
            // form data pribadi biasa disimpan, menghapus foto diam-diam.
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = $validated[$field];

            if (($employee->{$field} ?? null) === $value) {
                continue;
            }

            $changes[$field] = $value;
        }

        if ($changes === []) {
            return redirect()->route('ess.cv')->with('sukses', 'Tidak ada perubahan.');
        }

        try {
            $this->update->handle($employeeId, $changes, $this->currentActor($request));
        } catch (InvalidArgumentException|RuntimeException|ProfileChangeConflict $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('ess.cv')->with('sukses', 'CV berhasil diperbarui.');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $employee = $this->ownEmployee();
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        // validateWithBag('photo', ...) — halaman ini punya BEBERAPA form
        // sekaligus (data pribadi teks, unggah foto); tanpa bag bernama,
        // error unggah foto bisa salah tertampil seolah milik form teks
        // (atau sebaliknya) karena keduanya berbagi $errors default yang sama.
        $request->validateWithBag('photo', [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'photo.required' => 'Pilih berkas foto terlebih dahulu.',
            'photo.image' => 'Foto harus berupa berkas gambar.',
            'photo.mimes' => 'Foto hanya boleh berformat JPG atau PNG.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $stored = $request->file('photo')->store('pegawai/foto', 's3');

        abort_if($stored === false, 500, 'Gagal mengunggah foto — coba lagi.');

        try {
            $this->update->handle($employeeId, ['photo_path' => $stored], $this->currentActor($request));
        } catch (InvalidArgumentException|RuntimeException $e) {
            Storage::disk('s3')->delete($stored);

            return back()->with('gagal', $e->getMessage());
        }

        // Foto LAMA (kalau ada) dihapus SETELAH baris baru berhasil
        // tersimpan — supaya kalau update() di atas gagal, foto lama
        // yang masih dipakai tidak ikut hilang.
        if ($employee->photo_path !== null && $employee->photo_path !== $stored) {
            Storage::disk('s3')->delete($employee->photo_path);
        }

        return redirect()->route('ess.cv')->with('sukses', 'Foto profil berhasil diperbarui.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $employee = $this->ownEmployee();
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        if ($employee->photo_path === null) {
            return redirect()->route('ess.cv')->with('sukses', 'Tidak ada foto untuk dihapus.');
        }

        try {
            $this->update->handle($employeeId, ['photo_path' => null], $this->currentActor($request));
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        Storage::disk('s3')->delete($employee->photo_path);

        return redirect()->route('ess.cv')->with('sukses', 'Foto profil dihapus.');
    }

    /** Ditampilkan langsung (inline), BEDA dari downloadSk()/download lain di aplikasi ini yang selalu memaksa unduh. */
    public function photo(): StreamedResponse
    {
        $employee = $this->ownEmployee();

        abort_if($employee->photo_path === null, 404);

        return Storage::disk('s3')->response($employee->photo_path);
    }

    public function downloadSk(string $id): StreamedResponse
    {
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $row = DB::table('emp_decision_letters')->where('id', $id)->where('employee_id', $employeeId)->first();

        abort_if($row === null || $row->document_path === null, 404);

        return Storage::disk('s3')->download($row->document_path, $row->document_original_name);
    }

    /** @return array{employee: \stdClass, trainings: Collection<int, \stdClass>, certifications: Collection<int, \stdClass>, organizations: Collection<int, \stdClass>, awards: Collection<int, \stdClass>, decisionLetters: Collection<int, \stdClass>} */
    private function assembleCvData(): array
    {
        $employee = $this->ownEmployee();
        $employeeId = $this->actor->employeeId();

        return [
            'employee' => $employee,
            'trainings' => DB::table('emp_trainings')->where('employee_id', $employeeId)->orderByDesc('created_at')->get(),
            'certifications' => DB::table('emp_certifications')->where('employee_id', $employeeId)->orderByDesc('created_at')->get(),
            'organizations' => DB::table('emp_organizations')->where('employee_id', $employeeId)->orderByDesc('created_at')->get(),
            'awards' => DB::table('emp_awards')->where('employee_id', $employeeId)->orderByDesc('created_at')->get(),
            'decisionLetters' => DB::table('emp_decision_letters as d')
                ->leftJoin('emp_profile_change_requests as r', 'r.id', '=', 'd.profile_change_request_id')
                ->where('d.employee_id', $employeeId)
                ->orderByDesc('d.sk_date')
                ->select('d.*', 'r.status as perubahan_status')
                ->get(),
        ];
    }

    private function ownEmployee(): \stdClass
    {
        $employeeId = $this->actor->employeeId();

        abort_if($employeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $employee = DB::table('emp_employees as e')
            ->join('md_offices as o', 'o.id', '=', 'e.office_id')
            ->join('md_positions as p', 'p.id', '=', 'e.position_id')
            ->select('e.*', 'o.name as office_name', 'p.name as position_name')
            ->where('e.id', $employeeId)
            ->first();

        abort_if($employee === null, 404);

        return $employee;
    }

    private function currentActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
