<?php

namespace App\Http\Controllers;

use App\Models\ServiceNote;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceNoteController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'keuangan', 'teknisi'], true)) {
            abort(403);
        }

        $search = trim((string) $request->query('search', ''));
        $noteType = trim((string) $request->query('note_type', ''));
        $paymentMethod = trim((string) $request->query('payment_method', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = ServiceNote::query()
            ->with(['paidBy:id,name', 'pppUser:id,customer_name'])
            ->accessibleBy($user);

        if ($user->isTeknisi()) {
            $query->where('paid_by', $user->id);
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

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $query->whereDate('note_date', '>=', $dateFrom);
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $query->whereDate('note_date', '<=', $dateTo);
        }

        $serviceNotes = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('total'),
        ];

        $filters = [
            'search' => $search,
            'note_type' => $noteType,
            'payment_method' => $paymentMethod,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        return view('service-notes.index', compact('filters', 'serviceNotes', 'summary'));
    }

    public function print(ServiceNote $serviceNote): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $serviceNote->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->isTeknisi() && $serviceNote->paid_by !== $user->id) {
            abort(403);
        }

        $serviceNote->load([
            'creator:id,name',
            'owner:id,name,company_name',
            'owner.tenantSettings',
            'paidBy:id,name',
            'pppUser:id,customer_id,customer_name,username,ip_static,odp_pop',
        ]);

        return view('service-notes.print', compact('serviceNote'));
    }
}
