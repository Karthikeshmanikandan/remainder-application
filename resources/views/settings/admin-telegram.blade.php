@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Admin: Telegram Status</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white shadow rounded-lg p-6 flex flex-col items-center justify-center border-t-4 border-blue-500">
        <span class="text-4xl font-bold text-gray-800">{{ $totalConnected }}</span>
        <span class="text-sm font-medium text-gray-500 uppercase tracking-wide mt-1">Total Connected Accounts</span>
    </div>
    <div class="bg-white shadow rounded-lg p-6 flex flex-col items-center justify-center border-t-4 border-green-500">
        <span class="text-4xl font-bold text-gray-800">{{ $totalActive }}</span>
        <span class="text-sm font-medium text-gray-500 uppercase tracking-wide mt-1">Active Accounts</span>
    </div>
</div>

<div class="bg-white shadow overflow-hidden sm:rounded-md">
    <div class="px-5 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Recent Delivery Failures</h2>
    </div>
    
    @if($recentFailures->isEmpty())
        <div class="px-5 py-4 text-sm text-gray-500">No recent delivery failures recorded.</div>
    @else
        <ul class="divide-y divide-gray-200">
            @foreach($recentFailures as $failure)
                <li class="px-5 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">User: {{ $failure->user->name }} ({{ $failure->user->email }})</p>
                            <p class="text-xs text-red-600 mt-1 break-all">{{ Str::limit($failure->error_message, 150) }}</p>
                        </div>
                        <div class="text-xs text-gray-500">{{ $failure->created_at->diffForHumans() }}</div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
