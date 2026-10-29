<?php

namespace App\Jobs;

use App\Services\OrderLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireOrderReservationsJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function handle(OrderLifecycleService $lifecycle): void
    {
        $lifecycle->expireDueReservations();
    }
}
