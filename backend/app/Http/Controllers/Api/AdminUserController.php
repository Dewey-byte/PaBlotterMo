<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->transformAdminUser($user));

        return response()->json($admins);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'contactNumber' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'contact_number' => $validated['contactNumber'] ?? null,
            'role' => 'admin',
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'New admin user created successfully.',
            'user' => $this->transformAdminUser($user),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Only admin users can be updated from this endpoint.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contactNumber' => ['sometimes', 'nullable', 'string', 'max:20'],
            'newPassword' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }

        if (array_key_exists('contactNumber', $validated)) {
            $user->contact_number = $validated['contactNumber'];
        }

        if (array_key_exists('newPassword', $validated)) {
            $user->password = $validated['newPassword'];
        }

        $user->save();

        return response()->json([
            'message' => 'Admin account updated successfully.',
            'user' => $this->transformAdminUser($user),
        ]);
    }

    private function transformAdminUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'contactNumber' => $user->contact_number,
            'createdAt' => optional($user->created_at)->toISOString(),
            'updatedAt' => optional($user->updated_at)->toISOString(),
        ];
    }
}
