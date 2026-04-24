<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthUserEventPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthUserEventPublisher $authUserEventPublisher,
    ) {
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'birth_date' => ['required', 'date', 'before:today'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $userRole = Role::where('role', 'user')->first();

        if (!$userRole) {
            return response()->json([
                'message' => 'Роль user не найдена',
            ], 500);
        }

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'birth_date' => $validated['birth_date'],
            'password' => $validated['password'],
            'role_id' => $userRole->id,
            'status' => 1,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('role');

        $this->publishUserEventSafely('created', $user);

        return response()->json([
            'message' => 'Пользователь успешно зарегистрирован',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('role')->where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль'],
            ]);
        }

        if ((int) $user->status !== 1) {
            return response()->json([
                'message' => 'Аккаунт заблокирован',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Успешный вход',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('role'),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone,' . $user->id],
            'birth_date' => ['required', 'date', 'before:today'],
        ]);

        $user->update($validated);
        $updatedUser = $user->fresh()->load('role');

        $this->publishUserEventSafely('updated', $updatedUser);

        return response()->json([
            'message' => 'Профиль успешно обновлен',
            'user' => $updatedUser,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Вы успешно вышли из системы',
        ]);
    }

    private function publishUserEventSafely(string $type, User $user): void
    {
        try {
            match ($type) {
                'created' => $this->authUserEventPublisher->publishCreated($user),
                'updated' => $this->authUserEventPublisher->publishUpdated($user),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::warning('Failed to publish auth user event to RabbitMQ.', [
                'type' => $type,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
