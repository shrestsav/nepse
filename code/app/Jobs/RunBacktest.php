<?php

namespace App\Jobs;

use App\Models\BacktestRun;
use App\Services\Nepse\BacktestingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunBacktest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $backtestRunId,
    ) {
    }

    public function handle(BacktestingService $backtestingService): void
    {
        $run = BacktestRun::query()->findOrFail($this->backtestRunId);

        try {
            $backtestingService->run($run);
        } catch (Throwable $throwable) {
            $backtestingService->fail($run, $throwable->getMessage());
        }
    }
}
