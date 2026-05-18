<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthUserEventPublisher;
use Illuminate\Http\JsonResponse;
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

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:25'],
            'birth_date' => ['required', 'date', 'before:today'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $normalizedPhone = $this->normalizePhone($validated['phone']);
        $this->ensurePhoneIsUnique($normalizedPhone);

        $user = User::query()->create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $normalizedPhone,
            'birth_date' => $validated['birth_date'],
            'password' => $validated['password'],
            'role_id' => $this->resolveRoleId('user'),
            'status' => 1,
        ]);

        return $this->buildAuthenticatedResponse(
            $user,
            'Пользователь успешно зарегистрирован',
            201,
        );
    }

    public function registerOrganizer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $normalizedPhone = $this->normalizePhone($validated['phone']);
        $this->ensurePhoneIsUnique($normalizedPhone);

        $user = $this->createBusinessUser(
            roleName: 'organizer',
            companyName: $validated['company_name'],
            fullName: $validated['full_name'],
            email: $validated['email'],
            normalizedPhone: $normalizedPhone,
            password: $validated['password'],
        );

        return $this->buildAuthenticatedResponse(
            $user,
            'Организатор успешно зарегистрирован',
            201,
        );
    }

    public function registerVenueOwner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $normalizedPhone = $this->normalizePhone($validated['phone']);
        $this->ensurePhoneIsUnique($normalizedPhone);

        $user = $this->createBusinessUser(
            roleName: 'venue_owner',
            companyName: $validated['company_name'],
            fullName: $validated['full_name'],
            email: $validated['email'],
            normalizedPhone: $normalizedPhone,
            password: $validated['password'],
        );

        return $this->buildAuthenticatedResponse(
            $user,
            'Владелец площадки успешно зарегистрирован',
            201,
        );
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with(['role', 'organizerProfile'])
            ->where('email', $validated['email'])
            ->first();

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

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load(['role', 'organizerProfile']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing(['role', 'organizerProfile']);
        $isBusinessUser = in_array($user->role?->role, ['organizer', 'venue_owner'], true);

        $rules = [
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:25'],
            'birth_date' => $isBusinessUser
                ? ['nullable', 'date', 'before:today']
                : ['required', 'date', 'before:today'],
        ];

        if ($isBusinessUser) {
            $rules['company_name'] = ['required', 'string', 'min:2', 'max:255'];
        }

        $validated = $request->validate($rules);
        $normalizedPhone = $this->normalizePhone($validated['phone']);
        $this->ensurePhoneIsUnique($normalizedPhone, $user->id);

        $user->update([
            'full_name' => $validated['full_name'],
            'phone' => $normalizedPhone,
            'birth_date' => $validated['birth_date'] ?? null,
        ]);

        if ($isBusinessUser) {
            OrganizerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['company_name' => $validated['company_name']]
            );
        }

        $updatedUser = $user->fresh()->load(['role', 'organizerProfile']);

        $this->publishUserEventSafely('updated', $updatedUser);

        return response()->json([
            'message' => 'Профиль успешно обновлен',
            'user' => $updatedUser,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Вы успешно вышли из системы',
        ]);
    }

    private function resolveRoleId(string $roleName): int
    {
        $role = Role::query()->where('role', $roleName)->first();

        if (!$role) {
            throw new \RuntimeException("Роль {$roleName} не найдена");
        }

        return $role->id;
    }

    private function buildAuthenticatedResponse(User $user, string $message, int $status): JsonResponse
    {
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['role', 'organizerProfile']);

        $this->publishUserEventSafely('created', $user);

        return response()->json([
            'message' => $message,
            'token' => $token,
            'user' => $user,
        ], $status);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        if (strlen($digits) !== 11 || !str_starts_with($digits, '7')) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number must match the Russian format +7/8 (xxx) xxx-xx-xx.'],
            ]);
        }

        return '+' . $digits;
    }

    private function ensurePhoneIsUnique(string $normalizedPhone, ?int $ignoreUserId = null): void
    {
        $query = User::query()->where('phone', $normalizedPhone);

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['A user with this phone number already exists.'],
            ]);
        }
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

    private function createBusinessUser(
        string $roleName,
        string $companyName,
        string $fullName,
        string $email,
        string $normalizedPhone,
        string $password,
    ): User {
        $user = User::query()->create([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $normalizedPhone,
            'birth_date' => null,
            'password' => $password,
            'role_id' => $this->resolveRoleId($roleName),
            'status' => 1,
        ]);

        OrganizerProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'moderation_status' => 'pending',
        ]);

        return $user;
    }
}
