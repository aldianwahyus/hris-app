<?php

declare(strict_types=1);

namespace App\Shared\Audit\Domain;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;

/**
 * Pencatatan AKSES data sensitif — terpisah dari log perubahan.
 *
 * Mengapa dipisah: volume log akses jauh lebih besar (setiap kali slip
 * gaji dibuka), dan retensinya berbeda. Menggabungkan keduanya membuat
 * kueri audit perubahan menjadi lambat tanpa manfaat (DB-001 §3.1).
 *
 * Mengapa akses baca dicatat sama sekali: slip gaji dan data pribadi
 * adalah objek pengawasan UU PDP dan audit internal. Pertanyaan
 * "siapa saja yang pernah melihat data gaji Direksi?" harus dapat dijawab.
 */
final readonly class AccessLogEntry
{
    public string $id;

    public function __construct(
        public DateTimeImmutable $occurredAt,
        public string $actorId,
        public string $resourceType,
        public string $resourceId,
        public AccessType $accessType,
        public ?string $subjectId = null,
        public ?string $ipAddress = null,
    ) {
        $this->id = (string) Uuid7::generate();
    }

    /**
     * Akses terhadap data pegawai LAIN — sinyal utama bagi auditor.
     * Pegawai membuka slip gajinya sendiri adalah hal biasa; membuka
     * milik orang lain perlu justifikasi peran.
     */
    public function isThirdPartyAccess(): bool
    {
        return $this->subjectId !== null && $this->subjectId !== $this->actorId;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s.uP'),
            'actor_id' => $this->actorId,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'subject_id' => $this->subjectId,
            'access_type' => $this->accessType->value,
            'ip_address' => $this->ipAddress,
        ];
    }
}
