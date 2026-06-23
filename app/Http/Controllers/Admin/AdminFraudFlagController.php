<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFraudFlagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $flags = FraudFlag::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->with('user')
            ->latest()
            ->paginate(25);

        return response()->json([
            'flags' => $flags->through(fn ($f) => [
                'id'         => $f->id,
                'user'       => $f->user->name,
                'user_id'    => $f->user_id,
                'rule'       => $f->rule,
                'severity'   => $f->severity,
                'detail'     => $f->detail,
                'status'     => $f->status,
                'flagged_at' => $f->created_at->toDateTimeString(),
            ]),
            'meta' => [
                'current_page' => $flags->currentPage(),
                'last_page'    => $flags->lastPage(),
                'total'        => $flags->total(),
            ],
        ]);
    }

    public function resolve(Request $request, FraudFlag $flag): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'in:clear,ban'],
        ]);

        $flag->update([
            'status'      => $request->action === 'ban' ? 'banned' : 'cleared',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        if ($request->action === 'ban') {
            $flag->user->update(['is_banned' => true]);
        }

        return response()->json(['message' => 'Flag resolved.', 'flag' => $flag->fresh()]);
    }
}