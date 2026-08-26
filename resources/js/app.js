import './bootstrap';
import Swal from 'sweetalert2';

window.Swal = Swal;

/**
 * Pengganti confirm() bawaan browser — form dengan atribut data-confirm
 * menampilkan dialog SweetAlert2 sebelum benar-benar terkirim.
 * form.submit() (bukan requestSubmit()) sengaja dipakai agar submit
 * ulang TIDAK memicu event 'submit' lagi (mencegah gelang tak berujung),
 * sesuai perilaku baku HTMLFormElement.submit().
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            Swal.fire({
                title: 'Konfirmasi',
                text: form.dataset.confirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#0A7A5C',
                cancelButtonColor: '#5C706A',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
