<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AttendanceServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PayrollServiceProvider;
use App\Providers\SharedServiceProvider;
use App\Providers\SppdServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AccessServiceProvider::class,
    AttendanceServiceProvider::class,
    PayrollServiceProvider::class,
    HorizonServiceProvider::class,
    SppdServiceProvider::class,
];
