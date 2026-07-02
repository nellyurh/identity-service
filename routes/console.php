<?php

declare(strict_types=1);

use App\Interfaces\Console\RelayOutboxCommand;
use Illuminate\Support\Facades\Schedule;

// Relay the outbox every minute (platform operational cadence).
Schedule::command(RelayOutboxCommand::class)->everyMinute()->withoutOverlapping();
