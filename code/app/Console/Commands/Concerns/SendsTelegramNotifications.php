<?php

namespace App\Console\Commands\Concerns;

use App\Services\Notifications\TelegramNotifier;

trait SendsTelegramNotifications
{
    /**
     * @param list<string> $lines
     */
    protected function sendTelegramSummary(
        TelegramNotifier $notifier,
        string $title,
        bool $success,
        array $lines = [],
    ): void {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $command = $this->getName() ?: static::class;
        $timezone = (string) config('app.timezone', 'UTC');
        $finishedAt = now($timezone)->format('Y-m-d H:i:s');

        $messageLines = [
            $title,
            "Status: {$status}",
            "Command: {$command}",
            "Finished At: {$finishedAt} {$timezone}",
            ...$lines,
        ];

        $notifier->send(implode(PHP_EOL, $messageLines));
    }
}
