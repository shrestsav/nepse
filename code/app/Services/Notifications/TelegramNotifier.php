<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramNotifier
{
    public function send(string $message): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $botToken = trim((string) config('services.telegram.bot_token'));
        $chatId = trim((string) config('services.telegram.chat_id'));

        if ($botToken === '' || $chatId === '') {
            return false;
        }

        try {
            Http::asForm()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'disable_web_page_preview' => true,
                ])
                ->throw();

            return true;
        } catch (Throwable $throwable) {
            Log::warning('Telegram notification could not be sent.', [
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    private function enabled(): bool
    {
        return (bool) config('services.telegram.enabled', false);
    }
}
