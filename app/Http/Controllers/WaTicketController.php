<?php

namespace App\Http\Controllers;

use App\Jobs\SendTicketWaNotificationJob;
use App\Models\Outage;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaChatMessage;
use App\Models\WaConversation;
use App\Models\WaTicket;
use App\Models\WaTicketNote;
use App\Services\PushNotificationService;
use App\Services\WaGatewayService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WaTicketController extends Controller
{
    use LogsActivity;

    private const CS_ROLES = ['administrator', 'noc', 'it_support', 'cs'];

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        return view('wa-chat.tickets');
    }

    public function customerAutocomplete(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true)) {
            abort(403);
        }

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = PppUser::query()
            ->accessibleBy($user)
            ->where(function ($query) use ($q) {
                $query->where('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_id', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('nomor_hp', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'customer_name', 'customer_id', 'username', 'nomor_hp', 'alamat'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'text' => ($u->customer_name ?: $u->username).' — '.($u->nomor_hp ?: '-'),
                'customer_name' => $u->customer_name,
                'nomor_hp' => $u->nomor_hp,
                'customer_id' => $u->customer_id,
                'username' => $u->username,
                'alamat' => $u->alamat,
            ]);

        return response()->json($results);
    }

    public function datatable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        // Admin, CS, dan NOC mendapat highlight update belum dibaca dari teknisi
        $canSeeUnread = $user->isSuperAdmin()
            || ($user->isAdmin() && ! $user->isSubUser())
            || in_array($user->role, ['cs', 'noc'], true);

        $query = WaTicket::query()
            ->accessibleBy($user)
            ->with(['conversation:id,contact_phone,contact_name', 'assignedTo:id,name,nickname'])
            ->withCount(['notes as unread_count' => fn ($q) => $q->where('read_by_cs', false)])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to_id', $request->input('assigned_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(25);

        $data = $tickets->getCollection()->map(function (WaTicket $ticket) use ($canSeeUnread) {
            return [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'type' => $ticket->type,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'contact' => $ticket->conversation
                    ? ($ticket->conversation->contact_name ?? $ticket->conversation->contact_phone)
                    : ($ticket->manual_contact_name ?? '-'),
                'assigned_to' => $ticket->assignedTo
                    ? ($ticket->assignedTo->nickname ?? $ticket->assignedTo->name)
                    : '-',
                'created_at' => $ticket->created_at?->format('d/m/Y H:i'),
                'actions_url' => route('wa-tickets.show', $ticket),
                'has_unread_update' => $canSeeUnread && ($ticket->unread_count > 0),
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'total' => $tickets->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true)) {
            abort(403);
        }

        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:wa_conversations,id'],
            'customer_type' => ['nullable', 'string', 'in:ppp,hotspot'],
            'customer_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'type' => ['required', 'string', 'in:complaint,installation,troubleshoot,other'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
        ]);

        // Harus ada salah satu: conversation_id atau customer_id (tiket manual)
        if (empty($data['conversation_id']) && empty($data['customer_id'])) {
            return response()->json(['success' => false, 'message' => 'Pilih percakapan WA atau pilih pelanggan.'], 422);
        }

        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = WaConversation::findOrFail($data['conversation_id']);
            if (! $user->isSuperAdmin() && $conversation->owner_id !== $user->effectiveOwnerId()) {
                abort(403);
            }
        }

        // Resolve pelanggan untuk tiket manual
        $pppUser = null;
        if (! $conversation && ! empty($data['customer_id'])) {
            $pppUser = PppUser::where('id', $data['customer_id'])
                ->where('owner_id', $user->effectiveOwnerId())
                ->first();
            if (! $pppUser) {
                return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan.'], 422);
            }
        }

        $ownerId = $user->effectiveOwnerId();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('tickets', 'public');
        }

        $ticket = WaTicket::create([
            'owner_id' => $ownerId,
            'conversation_id' => $conversation?->id,
            'manual_contact_name' => $pppUser?->customer_name,
            'manual_contact_phone' => $pppUser?->nomor_hp,
            'customer_type' => $pppUser ? 'ppp' : ($data['customer_type'] ?? null),
            'customer_id' => $pppUser ? $pppUser->id : ($data['customer_id'] ?? null),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'type' => $data['type'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        // Catat timeline: tiket dibuat
        $ticket->notes()->create([
            'user_id' => $user->id,
            'type' => 'created',
            'meta' => 'Tiket dibuat oleh '.($user->nickname ?? $user->name),
        ]);

        // Dispatch job notifikasi WA — kirim ke pelanggan + grup, retry jika device offline
        try {
            $notifyPhone = $conversation?->contact_phone ?? $pppUser?->nomor_hp;
            $waSettings = TenantSettings::where('user_id', $ownerId)->first();

            if ($notifyPhone && $waSettings && $waSettings->hasWaConfigured()) {
                $groupId = trim((string) ($waSettings->wa_ticket_group_id ?? ''));
                $groupMsg = null;

                if ($groupId !== '') {
                    $customerName = $conversation?->contact_name ?? $pppUser?->customer_name ?? 'Pelanggan';
                    $typeLabel = match ($ticket->type) {
                        'complaint' => 'Pengaduan',
                        'installation' => 'Instalasi',
                        'troubleshoot' => 'Troubleshoot',
                        default => 'Lain-lain',
                    };
                    $priorityLabel = match ($ticket->priority) {
                        'high' => '🔴 Tinggi',
                        'low' => '🟢 Rendah',
                        default => '🟡 Normal',
                    };
                    $creatorName = $user->nickname ?? $user->name;
                    $groupMsg = "🎫 *Tiket Baru #{$ticket->id}*\n\n"
                        ."📋 Judul: {$ticket->title}\n"
                        ."👤 Pelanggan: {$customerName}\n"
                        ."🏷️ Tipe: {$typeLabel}\n"
                        ."⚡ Prioritas: {$priorityLabel}\n"
                        ."👩‍💼 Dibuat oleh: {$creatorName}\n\n"
                        ."Pantau tiket: {$ticket->publicUrl()}";
                }

                SendTicketWaNotificationJob::dispatch(
                    $ticket->id,
                    $ownerId,
                    $notifyPhone,
                    $groupId !== '' ? $groupId : null,
                    $groupMsg,
                );
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        // Push notification ke NOC/Admin saat tiket baru dibuat
        try {
            PushNotificationService::sendToOwnerStaff(
                $ownerId,
                'Tiket Baru #'.$ticket->id,
                $ticket->title.' — dibuat oleh '.($user->nickname ?? $user->name),
                ['url' => route('wa-tickets.show', $ticket), 'tag' => 'ticket-new-'.$ticket->id, 'icon' => '/branding/notify-ticket.png'],
                ['administrator', 'noc', 'it_support']
            );
        } catch (\Throwable) {
        }

        $this->logActivity('created', 'WaTicket', $ticket->id, $ticket->title, $ownerId);

        return response()->json(['success' => true, 'ticket_id' => $ticket->id]);
    }

    public function show(WaTicket $waTicket)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->role === 'teknisi' && $waTicket->assigned_to_id !== $user->id) {
            abort(403);
        }

        $waTicket->load([
            'conversation:id,contact_phone,contact_name',
            'assignedTo:id,name,nickname',
            'assignedBy:id,name',
            'notes.user:id,name,nickname,role',
        ]);

        // Admin, CS, dan NOC membuka tiket: tandai notif teknisi sebagai sudah dibaca
        $canMarkRead = $user->isSuperAdmin()
            || ($user->isAdmin() && ! $user->isSubUser())
            || in_array($user->role, ['cs', 'noc'], true);
        if ($canMarkRead) {
            $waTicket->notes()->where('read_by_cs', false)->update(['read_by_cs' => true]);
        }

        // Cek outage aktif di area pelanggan terkait tiket ini
        $relatedOutage = null;
        if ($waTicket->customer_type === 'ppp' && $waTicket->customer_id) {
            $pppUser = PppUser::find($waTicket->customer_id);
            if ($pppUser?->odp_id) {
                $relatedOutage = Outage::where('owner_id', $waTicket->owner_id)
                    ->whereIn('status', [Outage::STATUS_OPEN, Outage::STATUS_IN_PROGRESS])
                    ->whereHas('affectedAreas', fn ($q) => $q->where('odp_id', $pppUser->odp_id))
                    ->latest('started_at')
                    ->first();
            }
        }

        return view('wa-chat.ticket-show', compact('waTicket', 'user', 'relatedOutage'));
    }

    public function update(Request $request, WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->role === 'teknisi' && $waTicket->assigned_to_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $oldStatus = $waTicket->status;
        $wasResolved = $oldStatus !== 'resolved' && ($data['status'] ?? null) === 'resolved';

        if ($wasResolved) {
            $data['resolved_at'] = now();
        }

        $waTicket->update(array_filter($data, fn ($v) => $v !== null));

        // Catat timeline: perubahan status
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $waTicket->notes()->create([
                'user_id' => $user->id,
                'type' => 'status_change',
                'meta' => $oldStatus.' → '.$data['status'],
                // Jika teknisi yang ubah status → tandai belum dibaca CS
                'read_by_cs' => $user->role !== 'teknisi',
            ]);
        }

        // Notify customer if resolved
        if ($wasResolved) {
            try {
                $ownerId = $waTicket->owner_id;
                $settings = TenantSettings::where('user_id', $ownerId)->first();
                $resolvedPhone = $waTicket->conversation?->contact_phone ?? $waTicket->manual_contact_phone;
                if ($settings && $settings->hasWaConfigured() && $resolvedPhone) {
                    $service = WaGatewayService::forTenant($settings);
                    if ($service) {
                        $publicUrl = $waTicket->publicUrl();
                        $msg = "Tiket #{$waTicket->id} ({$waTicket->title}) telah diselesaikan.\n\nLihat ringkasan penanganan tiket:\n{$publicUrl}\n\nJika masih ada kendala, silakan hubungi kami kembali. Terima kasih.";
                        $service->sendMessage($resolvedPhone, $msg, ['event' => 'ticket_resolved']);
                    }
                }
            } catch (\Throwable) {
                // Non-blocking
            }
        }

        return response()->json(['success' => true]);
    }

    public function addNote(Request $request, WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->role === 'teknisi' && $waTicket->assigned_to_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if (! $request->filled('note') && ! $request->hasFile('image')) {
            return response()->json(['success' => false, 'message' => 'Isi catatan atau pilih foto.'], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ticket-notes', 'public');
        }

        $note = $waTicket->notes()->create([
            'user_id' => $user->id,
            'type' => 'note',
            'note' => $request->input('note'),
            'image_path' => $imagePath,
            // Catatan dari teknisi → belum dibaca CS
            'read_by_cs' => $user->role !== 'teknisi',
        ]);

        $note->load('user:id,name,nickname,role');

        // Push ke CS/NOC/Admin jika catatan dari teknisi
        if ($user->role === 'teknisi') {
            try {
                PushNotificationService::sendToOwnerStaff(
                    (int) $waTicket->owner_id,
                    'Update Tiket #'.$waTicket->id,
                    ($user->nickname ?? $user->name).': '.mb_substr($request->input('note', ''), 0, 80),
                    ['url' => route('wa-tickets.show', $waTicket), 'tag' => 'ticket-note-'.$waTicket->id, 'icon' => '/branding/notify-ticket.png'],
                    ['administrator', 'noc', 'cs', 'it_support']
                );
            } catch (\Throwable) {
            }
        }

        return response()->json([
            'success' => true,
            'note' => $this->formatNote($note),
        ]);
    }

    public function assign(Request $request, WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validate([
            'assigned_to_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $previousAssignee = $waTicket->assigned_to_id ? User::find($waTicket->assigned_to_id) : null;
        $assignee = User::findOrFail($data['assigned_to_id']);
        $isReassignment = $previousAssignee?->id !== null && $previousAssignee->id !== $assignee->id;

        if (! $user->isSuperAdmin() && $assignee->effectiveOwnerId() !== $user->effectiveOwnerId()) {
            return response()->json(['success' => false, 'message' => 'Teknisi bukan anggota tenant ini.'], 422);
        }

        $waTicket->update([
            'assigned_to_id' => $assignee->id,
            'assigned_by_id' => $user->id,
            'status' => $waTicket->status === 'open' ? 'in_progress' : $waTicket->status,
        ]);

        // Catat timeline: assignment
        $waTicket->notes()->create([
            'user_id' => $user->id,
            'type' => $isReassignment ? 'reassigned' : 'assigned',
            'meta' => $isReassignment
                ? 'Assign ulang teknisi: '.($previousAssignee->nickname ?? $previousAssignee->name).' -> '.($assignee->nickname ?? $assignee->name)
                : 'Assign teknisi: '.($assignee->nickname ?? $assignee->name),
        ]);

        // Notify teknisi via WA
        try {
            $ownerId = $waTicket->owner_id;
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured()) {
                $service = WaGatewayService::forTenant($settings);
                if ($service && $assignee->phone) {
                    $msg = $this->buildAssignmentNotificationMessage($waTicket, $assignee, $isReassignment);
                    $service->sendMessage($assignee->phone, $msg, ['event' => 'ticket_assigned']);
                }

                if ($service && $isReassignment && $previousAssignee?->phone) {
                    $msg = $this->buildReassignmentReleaseNotificationMessage($waTicket, $previousAssignee, $assignee);
                    $service->sendMessage($previousAssignee->phone, $msg, ['event' => 'ticket_reassigned_away']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        // Push notification to assigned teknisi
        try {
            PushNotificationService::sendToUser(
                $assignee,
                $isReassignment ? 'Penugasan Tiket Diperbarui' : 'Tiket Baru Ditugaskan',
                $isReassignment
                    ? 'Tiket #'.$waTicket->id.' "'.$waTicket->title.'" di-assign ulang ke Anda.'
                    : 'Tiket #'.$waTicket->id.' "'.$waTicket->title.'" di-assign ke Anda.',
                ['url' => route('wa-tickets.show', $waTicket), 'tag' => 'ticket-'.$waTicket->id, 'icon' => '/branding/notify-ticket.png']
            );
        } catch (\Throwable) {
        }

        if ($isReassignment && $previousAssignee) {
            try {
                PushNotificationService::sendToUser(
                    $previousAssignee,
                    'Penugasan Tiket Dialihkan',
                    'Tiket #'.$waTicket->id.' "'.$waTicket->title.'" telah dialihkan ke '.($assignee->nickname ?? $assignee->name).'.',
                    ['url' => route('wa-tickets.show', $waTicket), 'tag' => 'ticket-release-'.$waTicket->id, 'icon' => '/branding/notify-ticket.png']
                );
            } catch (\Throwable) {
            }
        }

        $this->logActivity('assigned', 'WaTicket', $waTicket->id, $waTicket->title, $waTicket->owner_id);

        return response()->json(['success' => true]);
    }

    public function destroy(WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $this->logActivity('deleted', 'WaTicket', $waTicket->id, $waTicket->title, $waTicket->owner_id);
        $waTicket->delete();

        return response()->json(['success' => true]);
    }

    private function formatNote(WaTicketNote $note): array
    {
        return [
            'id' => $note->id,
            'type' => $note->type,
            'note' => $note->note,
            'meta' => $note->meta,
            'image_url' => $note->image_path ? asset('storage/'.$note->image_path) : null,
            'user_name' => $note->user ? ($note->user->nickname ?? $note->user->name) : '-',
            'user_role' => $note->user?->role,
            'created_at' => $note->created_at?->format('d/m/Y H:i'),
        ];
    }

    private function buildAssignmentNotificationMessage(WaTicket $waTicket, User $assignee, bool $isReassignment = false): string
    {
        $assigneeName = $assignee->nickname ?: $assignee->name;
        $contact = $this->resolveTicketContactLabel($waTicket);
        $priority = $this->formatTicketPriorityLabel($waTicket->priority);
        $ticketUrl = route('wa-tickets.show', $waTicket);
        $openingLine = $isReassignment
            ? 'Penugasan tiket berikut telah diperbarui untuk Anda:'
            : 'Tiket berikut telah ditugaskan kepada Anda:';

        return "Halo {$assigneeName},\n\n"
            ."{$openingLine}\n\n"
            ."No. Tiket: #{$waTicket->id}\n"
            ."Judul: {$waTicket->title}\n"
            ."Pelanggan: {$contact}\n"
            ."Prioritas: {$priority}\n\n"
            ."Mohon segera tindak lanjuti tiket ini dan lakukan update status atau progres pekerjaan di RAFEN secara berkala agar tim dapat memantau penanganannya.\n\n"
            ."Buka tiket di RAFEN:\n{$ticketUrl}\n\n"
            .'Terima kasih.';
    }

    private function buildReassignmentReleaseNotificationMessage(WaTicket $waTicket, User $previousAssignee, User $newAssignee): string
    {
        $previousAssigneeName = $previousAssignee->nickname ?: $previousAssignee->name;
        $newAssigneeName = $newAssignee->nickname ?: $newAssignee->name;
        $contact = $this->resolveTicketContactLabel($waTicket);
        $ticketUrl = route('wa-tickets.show', $waTicket);

        return "Halo {$previousAssigneeName},\n\n"
            ."Penugasan tiket berikut sudah dialihkan ke {$newAssigneeName}:\n\n"
            ."No. Tiket: #{$waTicket->id}\n"
            ."Judul: {$waTicket->title}\n"
            ."Pelanggan: {$contact}\n\n"
            ."Anda tidak perlu menindaklanjuti tiket ini lagi, kecuali ada arahan baru dari admin atau tim koordinasi.\n\n"
            ."Lihat detail tiket di RAFEN:\n{$ticketUrl}\n\n"
            .'Terima kasih.';
    }

    private function resolveTicketContactLabel(WaTicket $waTicket): string
    {
        $conversation = $waTicket->conversation;

        if ($conversation) {
            return $conversation->contact_name
                ?: $conversation->contact_phone
                ?: 'Pelanggan belum tercatat';
        }

        return $waTicket->manual_contact_name
            ?: $waTicket->manual_contact_phone
            ?: 'Pelanggan belum tercatat';
    }

    private function formatTicketPriorityLabel(?string $priority): string
    {
        return match ($priority) {
            'high' => 'Tinggi',
            'low' => 'Rendah',
            default => 'Normal',
        };
    }

    /**
     * Ambil riwayat chat percakapan terkait tiket — bisa diakses teknisi yang di-assign.
     */
    public function ticketChatHistory(Request $request, WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorizeChatAccess($user, $waTicket);

        $conversation = $waTicket->conversation;
        if (! $conversation) {
            return response()->json(['messages' => [], 'has_conversation' => false]);
        }

        $afterId = $request->query('after');

        $query = $conversation->messages()->orderBy('created_at');
        if ($afterId !== null) {
            $query->where('id', '>', (int) $afterId);
        }

        $messages = $query->get()->map(fn ($msg) => $this->formatChatMessage($msg));

        return response()->json([
            'has_conversation' => true,
            'conversation_id' => $conversation->id,
            'contact_name' => $conversation->contact_name ?? $conversation->contact_phone,
            'contact_phone' => $conversation->contact_phone,
            'messages' => $messages,
        ]);
    }

    /**
     * Teknisi (atau CS/NOC) kirim pesan ke pelanggan langsung dari halaman tiket.
     * Prefix pesan otomatis menggunakan nickname atau nama pengguna.
     */
    public function ticketChatReply(Request $request, WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorizeChatAccess($user, $waTicket);

        $conversation = $waTicket->conversation;
        if (! $conversation) {
            return response()->json(['success' => false, 'message' => 'Tiket ini tidak memiliki percakapan WA terkait.'], 422);
        }

        $request->validate(['message' => ['required', 'string', 'max:4000']]);

        $ownerId = $waTicket->owner_id;
        $settings = TenantSettings::where('user_id', $ownerId)->first();

        if (! $settings || ! $settings->hasWaConfigured()) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.'], 422);
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return response()->json(['success' => false, 'message' => 'WA Gateway tidak tersedia.'], 422);
        }

        $senderName = $user->nickname ?: $user->name;
        $text = $request->message."\n- ".$senderName;

        $service->sendMessage($conversation->contact_phone, $text, [
            'event' => 'ticket_reply',
            'conversation_id' => $conversation->id,
            'ticket_id' => $waTicket->id,
        ]);

        $msg = $conversation->messages()->create([
            'owner_id' => $ownerId,
            'direction' => 'outbound',
            'message' => $text,
            'sender_name' => $senderName,
            'sender_id' => $user->id,
            'created_at' => now(),
        ]);

        $conversation->update([
            'last_message' => mb_substr($text, 0, 255),
            'last_message_at' => now(),
            'bot_paused_until' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $this->formatChatMessage($msg),
        ]);
    }

    /**
     * Otorisasi akses chat tiket: CS roles + teknisi yang di-assign.
     */
    private function authorizeChatAccess(User $user, WaTicket $waTicket): void
    {
        if (! $user->isSuperAdmin()
            && ! in_array($user->role, self::CS_ROLES, true)
            && $user->role !== 'teknisi') {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->role === 'teknisi' && $waTicket->assigned_to_id !== $user->id) {
            abort(403);
        }
    }

    private function formatChatMessage(WaChatMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'direction' => $msg->direction,
            'message' => $msg->message,
            'media_type' => $msg->media_type,
            'media_url' => $msg->media_path ? asset('storage/'.$msg->media_path) : null,
            'sender_name' => $msg->sender_name,
            'created_at' => $msg->created_at?->toISOString(),
            'created_at_human' => $msg->created_at?->format('H:i'),
            'created_at_date' => $msg->created_at?->format('d M Y'),
        ];
    }
}
