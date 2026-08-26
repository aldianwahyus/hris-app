<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Application;

final readonly class WorkflowSlaReport
{
    /**
     * @param  array<int, ReminderDue>  $reminders
     * @param  array<int, InstanceExpired>  $expired
     */
    public function __construct(
        public array $reminders,
        public array $expired,
    ) {}
}
