<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use App\Models\TelegramAccount;
use App\Services\TelegramAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TelegramSettingsController extends Controller
{
    public function __construct(private TelegramAccountService $accountService) {}

    public function edit(): View
    {
        $user = auth()->user();
        $user->load(['telegramAccount', 'notificationPreference']);

        return view('settings.telegram', [
            'user' => $user,
            'telegramAccount' => $user->telegramAccount,
            'preferences' => $user->notificationPreference,
            'botUsername' => null, // Would be nice to get this from a command or config, but we'll leave it simple.
        ]);
    }

    public function generateCode(): RedirectResponse
    {
        $code = $this->accountService->generateVerificationCode(auth()->user());

        return redirect()->route('settings.telegram')->with('success', 'New verification code generated.');
    }

    public function unlink(): RedirectResponse
    {
        $this->accountService->unlinkAccount(auth()->user());

        return redirect()->route('settings.telegram')->with('success', 'Telegram account disconnected.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'in_app_enabled' => ['boolean'],
            'telegram_enabled' => ['boolean'],
        ]);

        $user = auth()->user();
        $user->load('telegramAccount');

        // Cannot enable telegram if not verified
        if (($data['telegram_enabled'] ?? false) && (! $user->telegramAccount || ! $user->telegramAccount->isVerified())) {
            return redirect()->back()->with('error', 'Cannot enable Telegram notifications without a connected account.');
        }

        $preference = $user->notificationPreference()->firstOrCreate(['user_id' => $user->id]);
        $preference->update([
            'in_app_enabled' => $request->boolean('in_app_enabled'),
            'telegram_enabled' => $request->boolean('telegram_enabled'),
        ]);

        return redirect()->route('settings.telegram')->with('success', 'Notification preferences updated.');
    }

    /**
     * Admin view for telegram status
     */
    public function adminStatus(): View
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $totalConnected = TelegramAccount::whereNotNull('verified_at')->count();
        $totalActive = TelegramAccount::where('is_active', true)->count();

        $recentFailures = NotificationDelivery::where('channel', 'telegram')
            ->where('status', 'failed')
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        return view('settings.admin-telegram', compact('totalConnected', 'totalActive', 'recentFailures'));
    }
}
