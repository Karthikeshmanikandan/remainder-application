@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Telegram Settings</h1>
    <p class="text-sm text-gray-500">Manage your Telegram notifications and connection.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <!-- Telegram Connection Status -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Connection Status</h2>
        
        @if($telegramAccount && $telegramAccount->isVerified())
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0">
                    <span class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Connected</h3>
                    <p class="text-sm text-gray-500">Linked to Telegram ID: {{ $telegramAccount->telegram_user_id ?? 'Unknown' }}</p>
                    @if($telegramAccount->telegram_username)
                        <p class="text-sm text-gray-500">Username: {{ '@' . $telegramAccount->telegram_username }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-1">Connected on: {{ $telegramAccount->verified_at->format('Y-m-d H:i') }}</p>
                </div>
            </div>
            
            <form action="{{ route('settings.telegram.unlink') }}" method="POST" onsubmit="return confirm('Are you sure you want to unlink your Telegram account? You will stop receiving notifications.');">
                @csrf
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 text-sm">Disconnect Telegram</button>
            </form>
        @else
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0">
                    <span class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Not Connected</h3>
                    <p class="text-sm text-gray-500">You are not currently receiving Telegram notifications.</p>
                </div>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                <h4 class="text-sm font-medium text-gray-900 mb-2">How to connect:</h4>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>Generate a verification code below.</li>
                    <li>Open Telegram and start a chat with the bot.</li>
                    <li>Send the bot your verification code.</li>
                </ol>
                
                <div class="mt-4">
                    @if($telegramAccount && $telegramAccount->verification_code && $telegramAccount->verification_expires_at > now())
                        <div class="mb-4">
                            <p class="text-xs text-gray-500 mb-1">Your verification code (expires {{ $telegramAccount->verification_expires_at->diffForHumans() }}):</p>
                            <div class="bg-white border border-gray-300 rounded px-3 py-2 text-lg font-mono font-bold tracking-widest text-center">{{ $telegramAccount->verification_code }}</div>
                        </div>
                    @endif
                    
                    <form action="{{ route('settings.telegram.generate') }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm w-full">
                            {{ ($telegramAccount && $telegramAccount->verification_code) ? 'Generate New Code' : 'Generate Code' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <!-- Notification Preferences -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Notification Preferences</h2>
        
        <form action="{{ route('settings.telegram.preferences') }}" method="POST">
            @csrf
            
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input type="hidden" name="in_app_enabled" value="0">
                        <input id="in_app_enabled" name="in_app_enabled" type="checkbox" value="1" {{ ($preferences->in_app_enabled ?? true) ? 'checked' : '' }} class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="in_app_enabled" class="font-medium text-gray-700">In-App Notifications</label>
                        <p class="text-gray-500">Receive notifications in the application's notification centre.</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input type="hidden" name="telegram_enabled" value="0">
                        <input id="telegram_enabled" name="telegram_enabled" type="checkbox" value="1" {{ ($preferences->telegram_enabled ?? false) ? 'checked' : '' }} {{ (! $telegramAccount || ! $telegramAccount->isVerified()) ? 'disabled' : '' }} class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="telegram_enabled" class="font-medium text-gray-700">Telegram Notifications</label>
                        <p class="text-gray-500">Receive notifications via the Telegram bot.</p>
                        @if(! $telegramAccount || ! $telegramAccount->isVerified())
                            <p class="text-red-500 text-xs mt-1">You must connect your Telegram account first.</p>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">Save Preferences</button>
            </div>
        </form>
    </div>

</div>
@endsection
