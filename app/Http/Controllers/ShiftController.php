<?php

namespace App\Http\Controllers;

use App\Models\ShiftDefinition;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    private const ADMIN_ROLES = ['administrator'];

    private const VIEWER_ROLES = ['administrator', 'noc', 'it_support', 'cs'];

    private const ALL_SHIFT_ROLES = ['administrator', 'noc', 'it_support', 'cs', 'teknisi'];

    // ─────────────────────────────────────────────────────────────────────
    // VIEWS
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        $ownerId = $user->effectiveOwnerId();
        $subUsers = User::where(function ($q) use ($ownerId) {
            $q->where('id', $ownerId)
                ->orWhere('parent_id', $ownerId);
        })->orderBy('name')->get(['id', 'name', 'nickname', 'role']);

        return view('shifts.index', compact('subUsers'));
    }

    public function mySchedule(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, false);

        return view('shifts.my-schedule');
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCHEDULE JSON
    // ─────────────────────────────────────────────────────────────────────

    public function schedule(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, false);

        $from = $request->input('from', now()->startOfWeek()->toDateString());
        $to = $request->input('to', now()->endOfWeek()->toDateString());
        $userId = $request->input('user_id');

        $query = ShiftSchedule::query()
            ->accessibleBy($user)
            ->with(['user:id,name,nickname,role', 'shiftDefinition:id,name,start_time,end_time,color'])
            ->whereBetween('schedule_date', [$from, $to]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $schedules = $query->get()->map(function (ShiftSchedule $s) {
            return [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'user_name' => $s->user ? ($s->user->nickname ?? $s->user->name) : '-',
                'user_role' => $s->user?->role,
                'shift_id' => $s->shift_definition_id,
                'shift_name' => $s->shiftDefinition?->name,
                'shift_color' => $s->shiftDefinition?->color ?? '#3b82f6',
                'start_time' => $s->shiftDefinition?->start_time,
                'end_time' => $s->shiftDefinition?->end_time,
                'schedule_date' => $s->schedule_date?->toDateString(),
                'status' => $s->status,
                'notes' => $s->notes,
            ];
        });

        return response()->json(['data' => $schedules]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shift_definition_id' => ['required', 'integer', 'exists:shift_definitions,id'],
            'schedule_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $ownerId = $user->effectiveOwnerId();

        $schedule = ShiftSchedule::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'shift_definition_id' => $data['shift_definition_id'],
                'schedule_date' => $data['schedule_date'],
            ],
            [
                'owner_id' => $ownerId,
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json(['success' => true, 'id' => $schedule->id]);
    }

    public function bulkSchedule(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        $data = $request->validate([
            'schedules' => ['required', 'array', 'min:1', 'max:100'],
            'schedules.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'schedules.*.shift_definition_id' => ['required', 'integer', 'exists:shift_definitions,id'],
            'schedules.*.schedule_date' => ['required', 'date'],
        ]);

        $ownerId = $user->effectiveOwnerId();
        $count = 0;

        foreach ($data['schedules'] as $item) {
            ShiftSchedule::updateOrCreate(
                [
                    'user_id' => $item['user_id'],
                    'shift_definition_id' => $item['shift_definition_id'],
                    'schedule_date' => $item['schedule_date'],
                ],
                ['owner_id' => $ownerId, 'status' => 'scheduled']
            );
            $count++;
        }

        return response()->json(['success' => true, 'count' => $count]);
    }

    public function destroySchedule(ShiftSchedule $shiftSchedule): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        if (! $user->isSuperAdmin() && $shiftSchedule->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $shiftSchedule->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DEFINITIONS JSON
    // ─────────────────────────────────────────────────────────────────────

    public function definitions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, false);

        $defs = ShiftDefinition::query()
            ->accessibleBy($user)
            ->orderBy('start_time')
            ->get();

        return response()->json(['data' => $defs]);
    }

    public function storeDefinition(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'role' => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $def = ShiftDefinition::create([
            'owner_id' => $user->effectiveOwnerId(),
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'role' => $data['role'] ?? null,
            'color' => $data['color'] ?? '#3b82f6',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $def]);
    }

    public function updateDefinition(Request $request, ShiftDefinition $shiftDefinition): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        if (! $user->isSuperAdmin() && $shiftDefinition->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'role' => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $shiftDefinition->update($data);

        return response()->json(['success' => true, 'data' => $shiftDefinition->fresh()]);
    }

    public function destroyDefinition(ShiftDefinition $shiftDefinition): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        if (! $user->isSuperAdmin() && $shiftDefinition->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $shiftDefinition->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SWAP REQUESTS
    // ─────────────────────────────────────────────────────────────────────

    public function swapRequests(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, false);

        $query = ShiftSwapRequest::query()
            ->accessibleBy($user)
            ->with([
                'requester:id,name,nickname',
                'target:id,name,nickname',
                'fromSchedule.shiftDefinition:id,name,color',
                'toSchedule.shiftDefinition:id,name,color',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function requestSwap(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, false);

        $data = $request->validate([
            'from_schedule_id' => ['required', 'integer', 'exists:shift_schedules,id'],
            'to_schedule_id' => ['nullable', 'integer', 'exists:shift_schedules,id'],
            'target_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $fromSchedule = ShiftSchedule::findOrFail($data['from_schedule_id']);

        if (! $user->isSuperAdmin() && $fromSchedule->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Anda hanya bisa mengajukan tukar shift Anda sendiri.'], 422);
        }

        $swap = ShiftSwapRequest::create([
            'owner_id' => $user->effectiveOwnerId(),
            'requester_id' => $user->id,
            'target_id' => $data['target_id'] ?? null,
            'from_schedule_id' => $data['from_schedule_id'],
            'to_schedule_id' => $data['to_schedule_id'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        // Notify admin via WA
        try {
            $ownerId = $user->effectiveOwnerId();
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured() && $settings->business_phone) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $requesterName = $user->nickname ?? $user->name;
                    $date = $fromSchedule->schedule_date?->format('d/m/Y');
                    $shift = $fromSchedule->shiftDefinition?->name ?? '-';
                    $msg = "Permintaan Tukar Shift\n\nDari: {$requesterName}\nShift: {$shift} ({$date})\nAlasan: ".($data['reason'] ?? '-')."\n\nSilakan approve/reject di dashboard.";
                    $service->sendMessage($settings->business_phone, $msg, ['event' => 'shift_swap_request']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        return response()->json(['success' => true, 'id' => $swap->id]);
    }

    public function reviewSwap(Request $request, ShiftSwapRequest $shiftSwapRequest): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        if (! $user->isSuperAdmin() && $shiftSwapRequest->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
        ]);

        $isApprove = $data['action'] === 'approve';

        $shiftSwapRequest->update([
            'status' => $isApprove ? 'approved' : 'rejected',
            'reviewed_by_id' => $user->id,
            'reviewed_at' => now(),
        ]);

        // If approved and toSchedule is set, swap the assignments
        if ($isApprove && $shiftSwapRequest->to_schedule_id) {
            $from = $shiftSwapRequest->fromSchedule;
            $to = $shiftSwapRequest->toSchedule;

            if ($from && $to) {
                [$from->user_id, $to->user_id] = [$to->user_id, $from->user_id];
                $from->status = 'swapped';
                $to->status = 'swapped';
                $from->save();
                $to->save();
            }
        }

        // Notify requester via WA
        try {
            $ownerId = $shiftSwapRequest->owner_id;
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            $requester = $shiftSwapRequest->requester;
            if ($settings && $settings->hasWaConfigured() && $requester && $requester->phone) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $status = $isApprove ? 'disetujui' : 'ditolak';
                    $from = $shiftSwapRequest->fromSchedule;
                    $date = $from?->schedule_date?->format('d/m/Y') ?? '-';
                    $shift = $from?->shiftDefinition?->name ?? '-';
                    $msg = "Permintaan tukar shift Anda untuk shift {$shift} ({$date}) telah {$status}.";
                    $service->sendMessage($requester->phone, $msg, ['event' => 'shift_swap_reviewed']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        return response()->json(['success' => true]);
    }

    public function sendReminders(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->requireShiftAccess($user, true);

        $ownerId = $user->effectiveOwnerId();
        $sent = $this->dispatchRemindersForOwner($ownerId);

        return response()->json(['success' => true, 'sent' => $sent]);
    }

    public function dispatchRemindersForOwner(int $ownerId): int
    {
        $settings = TenantSettings::where('user_id', $ownerId)->first();
        if (! $settings || ! $settings->hasWaConfigured() || ! $settings->shift_feature_enabled) {
            return 0;
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return 0;
        }

        $tomorrow = now()->addDay()->toDateString();
        $schedules = ShiftSchedule::where('owner_id', $ownerId)
            ->whereDate('schedule_date', $tomorrow)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->with(['user:id,name,nickname,phone', 'shiftDefinition:id,name,start_time,end_time'])
            ->get();

        $recipients = [];
        foreach ($schedules as $schedule) {
            $employee = $schedule->user;
            if (! $employee || ! $employee->phone) {
                continue;
            }

            try {
                $shiftName = $schedule->shiftDefinition?->name ?? 'shift';
                $start = $schedule->shiftDefinition?->start_time ?? '-';
                $end = $schedule->shiftDefinition?->end_time ?? '-';
                $date = $schedule->schedule_date?->format('d/m/Y');
                $name = $employee->nickname ?? $employee->name;
                $msg = "Halo {$name},\n\nPengingat shift besok:\n- Shift: {$shiftName}\n- Tanggal: {$date}\n- Jam: {$start} - {$end}\n\nHadir tepat waktu ya! 🙏";
                $recipients[] = [
                    'phone' => $employee->phone,
                    'message' => $msg,
                    'name' => $name,
                ];
            } catch (\Throwable) {
                // Continue to next
            }
        }

        $sent = 0;
        if ($recipients !== []) {
            $uniqueRecipients = collect($recipients)
                ->unique(fn (array $item): string => (string) ($item['phone'] ?? ''))
                ->values()
                ->all();
            $result = $service->sendBulk($uniqueRecipients);
            $sent = (int) ($result['success'] ?? 0);
        }

        // Group summary if configured
        if ($settings->wa_shift_group_number && $schedules->isNotEmpty()) {
            try {
                $summary = "📋 *Jadwal Shift Besok ({$tomorrow})*\n\n";
                foreach ($schedules as $s) {
                    $name = $s->user ? ($s->user->nickname ?? $s->user->name) : '?';
                    $shift = $s->shiftDefinition?->name ?? '-';
                    $start = $s->shiftDefinition?->start_time ?? '-';
                    $end = $s->shiftDefinition?->end_time ?? '-';
                    $summary .= "• {$name}: {$shift} ({$start}-{$end})\n";
                }

                $groupId = trim((string) $settings->wa_shift_group_number);
                if ($groupId !== '' && str_ends_with(strtolower($groupId), '@g.us')) {
                    $service->sendGroupMessage($groupId, $summary, ['event' => 'shift_reminder_group']);
                } else {
                    $service->sendMessage($groupId, $summary, ['event' => 'shift_reminder_group']);
                }
            } catch (\Throwable) {
                // Non-blocking
            }
        }

        return $sent;
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function requireShiftAccess(User $user, bool $adminOnly): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($adminOnly && ! in_array($user->role, self::ADMIN_ROLES, true)) {
            abort(403);
        }

        if (! $adminOnly && ! in_array($user->role, self::ALL_SHIFT_ROLES, true)) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();
        $settings = TenantSettings::where('user_id', $ownerId)->value('shift_feature_enabled');

        if (! $settings) {
            abort(403, 'Fitur jadwal shift belum diaktifkan.');
        }
    }
}
