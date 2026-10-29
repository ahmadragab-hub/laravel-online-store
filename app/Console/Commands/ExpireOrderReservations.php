<?php

namespace App\Console\Commands;

use App\Jobs\ExpireOrderReservationsJob;
use Illuminate\Console\Command;

class ExpireOrderReservations extends Command
{
    protected $signature = 'orders:expire-reservations';

    protected $description =
        'Expire pending orders whose inventory reservations have elapsed.';

    public function handle(): int
    {
        ExpireOrderReservationsJob::dispatchSync();

        $this->info('Order reservation expiry job completed.');

        return self::SUCCESS;
    }
}
