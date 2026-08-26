<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

use DomainException;

final class NoActiveWorkflowDefinition extends DomainException
{
    public static function forDocumentType(string $documentType): self
    {
        return new self("Tidak ada definisi alur persetujuan aktif untuk jenis dokumen \"{$documentType}\".");
    }
}
