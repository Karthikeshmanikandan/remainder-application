<?php

namespace App\Http\Controllers;

use App\Services\TelegramUpdateService;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramUpdateService $updateService)
    {
        // Verify Telegram secret token
        $configuredSecret = config('services.telegram.webhook_secret');
        $incomingSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! $configuredSecret || $incomingSecret !== $configuredSecret) {
            abort(403, 'Unauthorized');
        }

        $update = $request->all();

        if (! empty($update)) {
            $updateService->handleUpdate($update);
        }

        return response()->json(['status' => 'ok']);
    }
}
