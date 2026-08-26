{{-- Partial form bersama EmployeeDirectoryController (hr_admin) & SystemAdminEmployeeController
     (SYSADMIN) — dua maker berbeda, field & alur usulan identik. Butuh var: $employee, $offices,
     $positions, $employeesForSupervisor, $updateRoute, $backRoute. --}}
<form method="POST" action="{{ $updateRoute }}">
  @csrf

  <div class="baris-bidang">
    <div class="bidang">
      <label for="office_id">Kantor</label>
      <select name="office_id" id="office_id">
        @foreach ($offices as $o)
          <option value="{{ $o->id }}" @selected($o->id === $employee->office_id)>{{ $o->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="position_id">Jabatan</label>
      <select name="position_id" id="position_id">
        @foreach ($positions as $p)
          <option value="{{ $p->id }}" @selected($p->id === $employee->position_id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="employment_status">Status kepegawaian</label>
      <select name="employment_status" id="employment_status">
        @foreach (['tetap' => 'Tetap', 'trainee' => 'Trainee', 'kontrak' => 'Kontrak', 'outsource' => 'Outsource'] as $val => $label)
          <option value="{{ $val }}" @selected($val === $employee->employment_status)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="permanent_date">Tanggal pengangkatan tetap</label>
      <input type="date" name="permanent_date" id="permanent_date" value="{{ $employee->permanent_date }}">
      <div class="ket">Wajib diisi bila status diubah/tetap menjadi "Tetap".</div>
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="person_grade">Person Grade</label>
      <input type="number" name="person_grade" id="person_grade" min="1" max="255" value="{{ $employee->person_grade }}">
    </div>

    <div class="bidang">
      <label for="job_grade">Job Grade</label>
      <input type="number" name="job_grade" id="job_grade" min="1" max="255" value="{{ $employee->job_grade }}">
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="tunjangan_jabatan_cents">Tunjangan Jabatan (Rp)</label>
      <input type="number" name="tunjangan_jabatan_cents" id="tunjangan_jabatan_cents" min="0" step="1"
        value="{{ (int) round($employee->tunjangan_jabatan_cents / 100) }}">
      <div class="ket">Tidak ada tabel baku — diisi manual per pegawai/jabatan.</div>
    </div>

    <div class="bidang">
      <label for="tunjangan_penyesuaian_cents">Tunjangan Penyesuaian (Rp)</label>
      <input type="number" name="tunjangan_penyesuaian_cents" id="tunjangan_penyesuaian_cents" min="0" step="1"
        value="{{ (int) round($employee->tunjangan_penyesuaian_cents / 100) }}">
      <div class="ket">Murni individual — tidak ada pola dari jabatan/grade.</div>
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="marital_status">Status Kawin (PTKP)</label>
      <select name="marital_status" id="marital_status">
        <option value="belum menikah" @selected($employee->marital_status === 'belum menikah')>Belum Menikah</option>
        <option value="menikah" @selected($employee->marital_status === 'menikah')>Menikah</option>
      </select>
    </div>

    <div class="bidang">
      <label for="tanggungan">Jumlah Tanggungan (PTKP, maks. 3)</label>
      <input type="number" name="tanggungan" id="tanggungan" min="0" max="3" value="{{ $employee->tanggungan }}">
      <div class="ket">Wajib diisi benar — menentukan golongan tarif TER PPh 21 (PMK 168/2023).</div>
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="supervisor_id">Atasan Langsung (untuk Struktur Organisasi)</label>
      <select name="supervisor_id" id="supervisor_id">
        <option value="">— Tidak ada (puncak bagan) —</option>
        @foreach ($employeesForSupervisor as $e)
          <option value="{{ $e->id }}" @selected($e->id === $employee->supervisor_id)>{{ $e->full_name }} ({{ $e->nrp }})</option>
        @endforeach
      </select>
      <div class="ket">Murni untuk tampilan bagan struktur organisasi — TIDAK memengaruhi wewenang persetujuan.</div>
    </div>

    <div class="bidang">
      <label for="division">Divisi</label>
      <input type="text" name="division" id="division" maxlength="100" value="{{ $employee->division }}" placeholder="mis. Divisi Operasional — relevan untuk Kantor Pusat">
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="agama">Agama</label>
      <select name="agama" id="agama">
        <option value="">— Pilih —</option>
        @foreach (['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $opt)
          <option value="{{ $opt }}" @selected($employee->agama === $opt)>{{ $opt }}</option>
        @endforeach
      </select>
    </div>

    <div class="bidang">
      <label for="tmt_pangkat">TMT Pangkat</label>
      <input type="date" name="tmt_pangkat" id="tmt_pangkat" value="{{ $employee->tmt_pangkat }}">
      <div class="ket">Tanggal mulai berlaku pangkat/jabatan terkini.</div>
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="nomor_ktp">Nomor KTP</label>
      <input type="text" name="nomor_ktp" id="nomor_ktp" maxlength="20" value="{{ $employee->nomor_ktp }}">
    </div>

    <div class="bidang">
      <label for="nomor_npwp">Nomor NPWP</label>
      <input type="text" name="nomor_npwp" id="nomor_npwp" maxlength="25" value="{{ $employee->nomor_npwp }}">
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="bpjs_tenaga_kerja">BPJS Ketenagakerjaan</label>
      <input type="text" name="bpjs_tenaga_kerja" id="bpjs_tenaga_kerja" maxlength="30" value="{{ $employee->bpjs_tenaga_kerja }}">
    </div>

    <div class="bidang">
      <label for="bpjs_kesehatan">BPJS Kesehatan</label>
      <input type="text" name="bpjs_kesehatan" id="bpjs_kesehatan" maxlength="30" value="{{ $employee->bpjs_kesehatan }}">
    </div>
  </div>

  <div class="baris-bidang">
    <div class="bidang">
      <label for="nomor_simpeda">Nomor Rekening Simpeda</label>
      <input type="text" name="nomor_simpeda" id="nomor_simpeda" maxlength="30" value="{{ $employee->nomor_simpeda }}">
    </div>

    <div class="bidang">
      <label for="nomor_tambora_rencana">Nomor Rekening Tambora Rencana</label>
      <input type="text" name="nomor_tambora_rencana" id="nomor_tambora_rencana" maxlength="30" value="{{ $employee->nomor_tambora_rencana }}">
    </div>
  </div>

  <button type="submit" class="btn">Ajukan Perubahan</button>
  <a href="{{ $backRoute }}" class="btn luar">Batal</a>
</form>
