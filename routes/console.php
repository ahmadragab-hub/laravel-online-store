<?php

use App\Jobs\ExpireOrderReservationsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireOrderReservationsJob)
    ->everyMinute()
    ->withoutOverlapping();
