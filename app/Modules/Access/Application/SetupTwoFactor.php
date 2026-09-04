<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Models\User;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Menyiapkan DAN mengonfirmasi 2FA (TOTP) — dua langkah terpisah
 * SENGAJA: `prepare()` hanya membangkitkan secret+QR (BELUM ditulis ke
 * `users`, secret disimpan sementara di sesi oleh TwoFactorController)
 * supaya pengguna yang batal di tengah jalan tidak meninggalkan secret
 * "menggantung" tanpa `two_factor_confirmed_at` — baru `confirm()`
 * menulis ke DB, dan HANYA setelah kode pertama terbukti benar (bukti
 * aplikasi authenticator pengguna benar-benar tersinkron).
 */
final class SetupTwoFactor
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditRepository $audit,
    ) {}

    /** @return array{0: string, 1: string} [secret, markup SVG kode QR] */
    public function prepare(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();
        $otpauthUrl = $this->google2fa->getQRCodeUrl('HCIS Bank NTB Syariah', (string) $user->email, $secret);

        $renderer = new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($otpauthUrl);

        return [$secret, $svg];
    }

    /**
     * @return array<int, string> kode pemulihan baru — HANYA dikembalikan
     *                            sekali ini, tidak pernah ditampilkan ulang APA ADANYA.
     */
    public function confirm(User $user, string $secret, string $code, ?AuditActor $actor = null): array
    {
        if (! $this->google2fa->verifyKey($secret, $code)) {
            throw new DomainException('Kode otentikasi tidak valid.');
        }

        $recoveryCodes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => new DateTimeImmutable,
        ])->save();

        if ($user->employee_id !== null) {
            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor ?? new AuditActor(actorId: $user->employee_id),
                auditableType: 'user_account',
                auditableId: $user->employee_id,
                action: AuditAction::TwoFactorEnabled,
            ));
        }

        return $recoveryCodes;
    }

    /** SYSADMIN mereset 2FA pengguna yang terkunci (kehilangan perangkat) — kolom dikosongkan. */
    public function reset(User $user, AuditActor $actor): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        if ($user->employee_id !== null) {
            $this->audit->append(new AuditEntry(
                occurredAt: new DateTimeImmutable,
                actor: $actor,
                auditableType: 'user_account',
                auditableId: $user->employee_id,
                action: AuditAction::TwoFactorReset,
            ));
        }
    }
}
