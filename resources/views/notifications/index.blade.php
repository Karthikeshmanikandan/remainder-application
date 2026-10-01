@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Notifications</h1>
        @if(auth()->user()->unreadNotifications->count())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded">
                    Mark All as Read
                </button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-10 text-center text-gray-400">
            <p class="text-lg">No notifications yet.</p>
        </div>
    @else
        <ul class="space-y-3">
            @foreach($notifications as $notification)
                @php $data = $notification->data; $isRead = !is_null($notification->read_at); @endphp
                <li class="bg-white rounded-lg border {{ $isRead ? 'border-gray-200' : 'border-blue-300' }} shadow-sm px-5 py-4 flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            @if(!$isRead)
                                <span class="inline-block w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                            @endif
                            <p class="{{ $isRead ? 'text-gray-600' : 'font-semibold text-gray-900' }} text-sm">
                                {{ $data['message'] ?? 'Task reminder.' }}
                            </p>
                        </div>
                        <div class="text-xs text-gray-500 space-x-3">
                            @if(!empty($data['task_title']))
                                <span>Task: <a href="{{ $data['task_url'] ?? '#' }}" class="text-blue-600 hover:underline">{{ $data['task_title'] }}</a></span>
                            @endif
                            @if(!empty($data['project_name']))
                                <span>Project: {{ $data['project_name'] }}</span>
                            @endif
                            @if(!empty($data['remind_at']))
                                <span>Reminder: {{ \Carbon\Carbon::parse($data['remind_at'])->format('Y-m-d H:i') }}</span>
                            @endif
                            @if(!empty($data['due_date']))
                                <span>Due: {{ \Carbon\Carbon::parse($data['due_date'])->format('Y-m-d') }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        @if(!$isRead)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 px-3 py-1.5 rounded">
                                    Mark read
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-gray-400">Read</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
@endsection
