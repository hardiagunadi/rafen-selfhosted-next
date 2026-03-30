<?php

namespace App\Http\Controllers;

use App\Models\WaKeywordRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WaKeywordRuleController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $rules = WaKeywordRule::query()
            ->accessibleBy($user)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return response()->json($rules);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'keywords'   => ['required', 'array', 'min:1'],
            'keywords.*' => ['required', 'string', 'max:100'],
            'reply_text' => ['required', 'string', 'max:2000'],
            'priority'   => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();

        $rule = WaKeywordRule::create([
            'owner_id'   => $user->effectiveOwnerId(),
            'keywords'   => $data['keywords'],
            'reply_text' => $data['reply_text'],
            'priority'   => $data['priority'] ?? 0,
            'is_active'  => $data['is_active'] ?? true,
        ]);

        return response()->json($rule, 201);
    }

    public function update(Request $request, WaKeywordRule $waKeywordRule)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $waKeywordRule->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validate([
            'keywords'   => ['sometimes', 'required', 'array', 'min:1'],
            'keywords.*' => ['required', 'string', 'max:100'],
            'reply_text' => ['sometimes', 'required', 'string', 'max:2000'],
            'priority'   => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $waKeywordRule->update($data);

        return response()->json($waKeywordRule->fresh());
    }

    public function destroy(WaKeywordRule $waKeywordRule)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $waKeywordRule->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $waKeywordRule->delete();

        return response()->json(['ok' => true]);
    }
}
