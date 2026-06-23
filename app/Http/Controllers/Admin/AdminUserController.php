<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('wallet')
            ->when($request->search, fn ($q, $search) =>
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))
            )
            ->latest()
            ->paginate(25);

        return response()->json([
            'users' => $users->through(fn ($u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'phone'           => $u->phone,
                'kyc_status'      => $u->kyc_status,
                'is_banned'       => $u->is_banned,
                'is_admin'        => $u->is_admin,
                'balance'         => $u->wallet?->balance ?? 0,
                'total_deposited' => $u->wallet?->total_deposited ?? 0,
                'total_won'       => $u->wallet?->total_won ?? 0,
                'created_at'      => $u->created_at->toDateString(),
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    public function toggleBan(User $user): JsonResponse
    {
        $user->update(['is_banned' => ! $user->is_banned]);

        return response()->json([
            'message' => $user->is_banned ? 'User banned.' : 'User unbanned.',
            'user'    => $user,
        ]);
    }
}