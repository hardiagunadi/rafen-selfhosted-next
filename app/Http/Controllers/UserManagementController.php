<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private function authorizeAccess(User $authUser): void
    {
        // Sub-users cannot manage users
        if ($authUser->isSubUser()) {
            abort(403);
        }
        // Only super admin or tenant admin
        if (! $authUser->isSuperAdmin() && ! $authUser->isAdmin()) {
            abort(403);
        }
    }

    private function authorizeTarget(User $authUser, User $targetUser): void
    {
        if ($authUser->isSuperAdmin()) {
            return;
        }
        // Tenant admin can only manage their own sub-users
        if ($targetUser->parent_id !== $authUser->id) {
            abort(403);
        }
    }

    public function index(): View
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        return view('users.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        $search = $request->input('search.value', '');

        $query = User::query()
            ->with('parent')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('parent_id', $user->id))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%");
            }))
            ->latest();

        $total = User::query()
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('parent_id', $user->id))
            ->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'nickname' => $r->nickname ?: '-',
                'email' => $r->email,
                'phone' => $r->phone ?: '-',
                'role' => strtoupper(str_replace('_', ' ', $r->role ?? '-')),
                'tenant' => $r->is_super_admin
                    ? '<span class="badge badge-dark">Super Admin</span>'
                    : ($r->parent_id === null
                        ? '<span class="badge badge-primary">Tenant Admin</span>'
                        : e($r->parent->name ?? '-')),
                'last_login_at' => $r->last_login_at?->format('Y-m-d H:i:s') ?? '-',
                'edit_url' => route('users.edit', $r->id),
                'destroy_url' => route('users.destroy', $r->id),
            ]),
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        $roles = $user->isSuperAdmin() ? $this->roles() : $this->subUserRoles();

        return view('users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($user);

        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);

        if (! $user->isSuperAdmin()) {
            // Tenant admin creates sub-users under themselves
            $data['parent_id'] = $user->id;
            // Inherit subscription from parent tenant
            $data['subscription_status'] = $user->subscription_status;
            $data['subscription_expires_at'] = $user->subscription_expires_at;
            $data['subscription_plan_id'] = $user->subscription_plan_id;
            $data['subscription_method'] = $user->subscription_method ?: User::SUBSCRIPTION_METHOD_MONTHLY;
            $data['license_max_mikrotik'] = $user->license_max_mikrotik;
            $data['license_max_ppp_users'] = $user->license_max_ppp_users;
            $data['trial_days_remaining'] = (int) ($user->trial_days_remaining ?? 0);
            // Prevent creating administrator-level or super admin accounts
            if (($data['role'] ?? '') === 'administrator') {
                $data['role'] = 'it_support';
            }
            unset($data['is_super_admin']);
        }

        User::create($data);

        return redirect()->route('users.index')->with('status', 'Pengguna dibuat.');
    }

    public function edit(User $user): View
    {
        $authUser = auth()->user();
        $this->authorizeAccess($authUser);
        $this->authorizeTarget($authUser, $user);

        $roles = $authUser->isSuperAdmin() ? $this->roles() : $this->subUserRoles();

        return view('users.edit', ['user' => $user, 'roles' => $roles]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $authUser = auth()->user();
        $this->authorizeAccess($authUser);
        $this->authorizeTarget($authUser, $user);

        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        if (! $authUser->isSuperAdmin()) {
            // Tenant admin cannot change parent_id or promote to administrator
            unset($data['parent_id'], $data['is_super_admin']);
            if (($data['role'] ?? '') === 'administrator') {
                unset($data['role']);
            }
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Pengguna diperbarui.');
    }

    public function destroy(User $user): JsonResponse|RedirectResponse
    {
        $authUser = auth()->user();
        $this->authorizeAccess($authUser);
        $this->authorizeTarget($authUser, $user);

        // Tenant admin accounts must be deleted via the Tenants page (which enforces cascade checks)
        if ($user->isAdmin() && $user->parent_id === null) {
            $message = 'Akun tenant admin tidak bisa dihapus dari halaman ini. Gunakan menu Tenants untuk menghapus tenant.';
            if (request()->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('users.index')->with('error', $message);
        }

        $user->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Pengguna dihapus.']);
        }

        return redirect()->route('users.index')->with('status', 'Pengguna dihapus.');
    }

    private function roles(): array
    {
        return [
            'administrator' => 'Administrator',
            'it_support' => 'IT Support',
            'noc' => 'NOC',
            'keuangan' => 'Keuangan',
            'teknisi' => 'Teknisi',
            'cs' => 'Customer Services',
        ];
    }

    private function subUserRoles(): array
    {
        return [
            'it_support' => 'IT Support',
            'noc' => 'NOC',
            'keuangan' => 'Keuangan',
            'teknisi' => 'Teknisi',
            'cs' => 'Customer Services',
        ];
    }
}
