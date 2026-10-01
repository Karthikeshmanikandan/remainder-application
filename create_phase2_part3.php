<?php

function create_file($path, $content)
{
    $dir = dirname(__DIR__.'/'.$path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(__DIR__.'/'.$path, $content);
    echo "Created: $path\n";
}

// LAYOUT
create_file('resources/views/layouts/app.blade.php', <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Task Reminder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @auth
    <nav class="bg-white shadow-sm mb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="shrink-0 flex items-center font-bold text-xl text-blue-600">
                        TaskApp
                    </div>
                    <div class="hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8">
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="{{ route('projects.index') }}" class="{{ request()->routeIs('projects.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Projects</a>
                        <a href="{{ route('tasks.index') }}" class="{{ request()->routeIs('tasks.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Tasks</a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Users</a>
                        @endif
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-sm text-gray-500 mr-4">{{ auth()->user()->name }} ({{ auth()->user()->role->name }})</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-red-600 hover:text-red-900">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    @endauth

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 @guest pt-8 @endguest">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
HTML);

// LOGIN VIEW
create_file('resources/views/auth/login.blade.php', <<<'HTML'
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
HTML);

// USERS VIEWS
create_file('resources/views/users/index.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Users</h1>
    <a href="{{ route('users.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Create User</a>
</div>
<div class="bg-white shadow overflow-hidden sm:rounded-md">
    <ul class="divide-y divide-gray-200">
        @foreach($users as $user)
        <li>
            <div class="px-4 py-4 flex items-center justify-between sm:px-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-blue-600">{{ $user->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $user->email }} | Role: {{ $user->role->name }} | Status: {{ $user->status->name }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                </div>
            </div>
        </li>
        @endforeach
    </ul>
</div>
{{ $users->links() }}
@endsection
HTML);

create_file('resources/views/users/create.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Create User</h1>
<form action="{{ route('users.store') }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf
    @include('users._form')
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Save User</button>
</form>
@endsection
HTML);

create_file('resources/views/users/edit.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit User</h1>
<form action="{{ route('users.update', $user) }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf @method('PUT')
    @include('users._form', ['user' => $user])
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Update User</button>
</form>
@endsection
HTML);

create_file('resources/views/users/_form.blade.php', <<<'HTML'
@if($errors->any())
    <div class="text-red-600 mb-4"><ul>@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div>
@endif
<div class="grid grid-cols-1 gap-4">
    <div>
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>Role</label>
        <select name="role" class="w-full border rounded px-3 py-2">
            @foreach(\App\Enums\UserRole::cases() as $role)
                <option value="{{ $role->value }}" {{ old('role', $user->role->value ?? '') === $role->value ? 'selected' : '' }}>{{ $role->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Status</label>
        <select name="status" class="w-full border rounded px-3 py-2">
            @foreach(\App\Enums\UserStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ old('status', $user->status->value ?? '') === $status->value ? 'selected' : '' }}>{{ $status->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="pt-4 border-t">
        <label>Password {{ isset($user) ? '(leave blank to keep current)' : '' }}</label>
        <input type="password" name="password" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>Confirm Password</label>
        <input type="password" name="password_confirmation" class="w-full border rounded px-3 py-2">
    </div>
</div>
HTML);

// TESTS
create_file('tests/Feature/AuthTest.php', <<<'PHP'
<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\UserStatus;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/tasks')->assertRedirect('/login');
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!'), 'status' => UserStatus::ACTIVE]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login()
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!'), 'status' => UserStatus::INACTIVE]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);
        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_invalid_login()
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);
        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
PHP);

create_file('tests/Feature/UserTest.php', <<<'PHP'
<?php
namespace Tests\Feature;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_users()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        
        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'manager',
            'status' => 'active',
        ]);
        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_non_admin_cannot_manage_users()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        
        $this->actingAs($manager)->get('/users')->assertStatus(403);
        $this->actingAs($manager)->post('/users', [])->assertStatus(403);
    }
}
PHP);

create_file('database/factories/UserFactory.php', <<<'PHP'
<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Enums\UserRole;
use App\Enums\UserStatus;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::EMPLOYEE,
            'status' => UserStatus::ACTIVE,
        ];
    }
}
PHP);
