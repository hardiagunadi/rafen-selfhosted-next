<?php

namespace App\Http\Controllers;

use App\Models\ServiceNote;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceNoteController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'keuangan', 'teknisi'], true)) {
            abort(403);
        }

        $search = trim((string) $request->query('search', ''));
        $noteType = trim((string) $request->query('note_type', ''));
        $paymentMethod = trim((string) $request->query('payment_method', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = ServiceNote::query()
            ->with(['creator:id,name', 'paidBy:id,name', 'pppUser:id,customer_name'])
            ->accessibleBy($user);

        if ($user->isTeknisi()) {
            $query->where(function ($builder) use ($user): void {
                $builder->where('paid_by', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('document_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%");
            });
        }

        if (in_array($noteType, ['aktivasi', 'pemasangan', 'perbaikan', 'lainnya'], true)) {
            $query->where('note_type', $noteType);
        }

        if (in_array($paymentMethod, ['cash', 'transfer', 'lainnya'], true)) {
            $query->where('payment_method', $paymentMethod);
        }

        if (in_array($status, [ServiceNote::STATUS_PENDING, ServiceNote::STATUS_PAID], true)) {
            $query->where('status', $status);
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $query->whereDate('note_date', '>=', $dateFrom);
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $query->whereDate('note_date', '<=', $dateTo);
        }

        $serviceNotes = $query
            ->orderByRaw('paid_at IS NULL DESC')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'count' => (clone $query)->count(),
            'pending_count' => (clone $query)->where('status', ServiceNote::STATUS_PENDING)->count(),
            'paid_total' => (float) (clone $query)->where('status', ServiceNote::STATUS_PAID)->sum('total'),
        ];

        $filters = [
            'search' => $search,
            'note_type' => $noteType,
            'payment_method' => $paymentMethod,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        return view('service-notes.index', compact('filters', 'serviceNotes', 'summary'));
    }

    public function print(ServiceNote $serviceNote): View
    {
        $this->authorizeAccess(auth()->user(), $serviceNote);

        $serviceNote->load([
            'creator:id,name',
            'owner:id,name,company_name',
            'owner.tenantSettings',
            'paidBy:id,name',
            'pppUser:id,customer_id,customer_name,username,ip_static,odp_pop',
        ]);

        return view('service-notes.print', compact('serviceNote'));
    }

    public function confirmTransfer(Request $request, ServiceNote $serviceNote): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorizeAccess($user, $serviceNote);

        if ($serviceNote->payment_method !== 'transfer') {
            abort(404);
        }

        if (! $serviceNote->isPending()) {
            return redirect()
                ->route('service-notes.print', $serviceNote)
                ->with('status', 'Transfer untuk nota ini sudah pernah dikonfirmasi.');
        }

        $serviceNote->update([
            'status' => ServiceNote::STATUS_PAID,
            'paid_by' => $user->id,
            'paid_at' => now(),
        ]);

        $this->logActivity(
            'service_note_transfer_confirmed',
            'ServiceNote',
            $serviceNote->id,
            $serviceNote->document_number,
            (int) $serviceNote->owner_id,
            [
                'payment_method' => $serviceNote->payment_method,
                'confirmed_by' => $user->id,
                'total' => $serviceNote->total,
            ],
        );

        return redirect()
            ->route('service-notes.print', $serviceNote)
            ->with('status', 'Transfer nota layanan berhasil dikonfirmasi.');
    }

    private function authorizeAccess(User $user, ServiceNote $serviceNote): void
    {
        if (! $user->isSuperAdmin() && $serviceNote->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if (! $user->isTeknisi()) {
            return;
        }

        $isOwnedByTeknisi = $serviceNote->paid_by === $user->id || $serviceNote->created_by === $user->id;

        if (! $isOwnedByTeknisi) {
            abort(403);
        }
    }
}
