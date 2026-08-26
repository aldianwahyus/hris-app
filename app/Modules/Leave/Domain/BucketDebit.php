<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

/** Rencana pengurangan satu kantong — hasil keputusan LeaveBalanceLedger. */
final readonly class BucketDebit
{
    public function __construct(
        public BucketType $bucketType,
        public float $days,
    ) {}
}
