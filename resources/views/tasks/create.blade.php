@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Create Task</h1>
<form action="{{ route('tasks.store') }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf
    @include('tasks._form')
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Save Task</button>
</form>
@endsection