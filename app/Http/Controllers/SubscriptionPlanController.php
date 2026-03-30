<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return view('subscription-plans.index', compact('plans'));
    }

    public function create()
    {
        return view('subscription-plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_mikrotik' => 'required|integer|min:-1',
            'max_ppp_users' => 'required|integer|min:-1',
            'max_vpn_peers' => 'required|integer|min:-1',
            'features_text' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $featuresText = $validated['features_text'] ?? '';
        $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $featuresText))));
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        unset($validated['features_text']);

        $validated['slug'] = Str::slug($validated['name']);

        // Check if slug already exists
        $slugCount = SubscriptionPlan::where('slug', 'like', $validated['slug'] . '%')->count();
        if ($slugCount > 0) {
            $validated['slug'] .= '-' . ($slugCount + 1);
        }

        SubscriptionPlan::create($validated);

        return redirect()->route('super-admin.subscription-plans.index')
            ->with('success', 'Paket langganan berhasil dibuat.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        return view('subscription-plans.edit', ['plan' => $subscriptionPlan]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_mikrotik' => 'required|integer|min:-1',
            'max_ppp_users' => 'required|integer|min:-1',
            'max_vpn_peers' => 'required|integer|min:-1',
            'features_text' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $featuresText = $validated['features_text'] ?? '';
        $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $featuresText))));
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        unset($validated['features_text']);

        $subscriptionPlan->update($validated);

        return redirect()->route('super-admin.subscription-plans.index')
            ->with('success', 'Paket langganan berhasil diperbarui.');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        // Check if there are active subscriptions
        if ($subscriptionPlan->subscriptions()->active()->exists()) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Tidak dapat menghapus paket yang masih memiliki langganan aktif.'], 422);
            }
            return back()->with('error', 'Tidak dapat menghapus paket yang masih memiliki langganan aktif.');
        }

        $subscriptionPlan->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Paket langganan berhasil dihapus.']);
        }

        return redirect()->route('super-admin.subscription-plans.index')
            ->with('success', 'Paket langganan berhasil dihapus.');
    }

    public function toggleActive(SubscriptionPlan $subscriptionPlan): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $subscriptionPlan->update(['is_active' => !$subscriptionPlan->is_active]);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Status paket langganan berhasil diubah.']);
        }

        return back()->with('success', 'Status paket langganan berhasil diubah.');
    }
}
