@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit Task</h1>
<form action="{{ route('tasks.update', $task) }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf @method('PUT')
    @include('tasks._form', ['task' => $task])
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Update Task</button>
</form>
@endsection