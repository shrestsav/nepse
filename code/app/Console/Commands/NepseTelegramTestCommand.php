<?php

namespace App\Console\Commands;

use App\Services\Notifications\TelegramNotifier;
use Illuminate\Console\Command;

class NepseTelegramTestCommand extends Command
{
    protected $signature = 'nepse:telegram-test
        {--message= : Optional custom message to send}';

    protected $description = 'Send a Telegram test message using configured bot credentials';

    public function handle(TelegramNotifier $telegramNotifier): int
    {
        $message = trim((string) $this->option('message'));

        if ($message === '') {
            $message = sprintf(
                'NEPSE Telegram test message at %s (%s)',
                now()->format('Y-m-d H:i:s'),
                (string) config('app.timezone', 'UTC'),
            );
        }

        $sent = $telegramNotifier->send($message);

        if (! $sent) {
            $this->components->error(
                'Telegram message could not be sent. Check TELEGRAM_NOTIFICATIONS_ENABLED, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, and network access.',
            );

            return self::FAILURE;
        }

        $this->components->info('Telegram test message sent successfully.');

        return self::SUCCESS;
    }
}
