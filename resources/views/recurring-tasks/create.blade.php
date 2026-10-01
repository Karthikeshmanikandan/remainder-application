@extends('layouts.app')

@section('content')
<div class="mb-6">
    <a href="{{ route('recurring-tasks.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Recurring Tasks</a>
    <h1 class="text-2xl font-semibold text-gray-900 mt-2">Create Recurring Task</h1>
</div>

<div class="bg-white shadow overflow-hidden sm:rounded-md p-6">
    <form action="{{ route('recurring-tasks.store') }}" method="POST">
        @csrf
        @include('recurring-tasks._form')
    </form>
</div>
@endsection
