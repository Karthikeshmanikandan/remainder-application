@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit Project</h1>
<form action="{{ route('projects.update', $project) }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf @method('PUT')
    @include('projects._form', ['project' => $project])
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Update Project</button>
</form>
@endsection