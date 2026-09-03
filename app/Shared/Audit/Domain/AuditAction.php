<?php

declare(strict_types=1);

namespace App\Shared\Audit\Domain;

/**
 * Jenis tindakan yang direkam audit trail.
 *
 * Sengaja berupa enum tertutup: daftar tindakan harus terbatas dan
 * eksplisit agar laporan audit dapat dikelompokkan secara konsisten.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Delegated = 'delegated';
    case Escalated = 'escalated';
    case Expired = 'expired';
    case Executed = 'executed';
    case Verified = 'verified';
    case Reminded = 'reminded';
    case PasswordReset = 'password_reset';
    case AttendanceRecorded = 'attendance_recorded';
    case RoleGranted = 'role_granted';
    case RoleRevoked = 'role_revoked';

    // Peran↔izin (role_has_permissions) — BEDA dari RoleGranted/RoleRevoked
    // di atas yang mencatat pengguna↔peran. Lihat RoleFeatureMapController.
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';
    case ParameterValueAdded = 'parameter_value_added';
    case Disbursed = 'disbursed';
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case LoggedOut = 'logged_out';
    case Reopened = 'reopened';

    // Tanda Tangan Elektronik (internal) — lihat App\Shared\Signature.
    case Signed = 'signed';

    /**
     * Tindakan yang berdampak finansial atau menyangkut hak pegawai.
     * Wajib mencantumkan dasar (context_ref) — mis. nomor SPKL atau SK.
     */
    public function requiresContext(): bool
    {
        return in_array($this, [
            self::Approved,
            self::Rejected,
            self::Executed,
            self::Cancelled,
            self::Expired,
            self::Disbursed,
            self::Reopened,
            self::Signed,
        ], true);
    }
}
