@extends('layouts.app')
@section('content')
<div class="max-w-md mx-auto bg-white rounded p-6 shadow-sm mt-10">
    <h2 class="text-2xl font-bold text-center mb-6 text-gray-900">Sign in to your account</h2>
    @if($errors->any())
        <div class="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm">
            <ul>@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700">Email address</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" required class="mt-1 w-full border rounded px-3 py-2">
        </div>
        <div class="flex items-center">
            <input type="checkbox" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            <label class="ml-2 block text-sm text-gray-900">Remember me</label>
        </div>
        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
            Sign in
        </button>
    </form>
</div>
@endsection