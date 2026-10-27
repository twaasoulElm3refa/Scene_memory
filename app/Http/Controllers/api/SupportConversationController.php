<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Mail\SupportNotification;
use App\Models\Contacts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportConversationController extends Controller
{
    use ApiResponse;

    public function create(ContactRequest $request)
    {
        $data = $request->validated();
        $user = auth('sanctum')->user();
        $key = $data['submission_key'] ?? (string) Str::uuid();
        $created = false;

        $ticket = DB::transaction(function () use ($data, $user, $key, &$created) {
            $ticket = Contacts::query()->where('submission_key', $key)->first();
            if ($ticket) {
                abort_unless($ticket->email === $data['email'] && $ticket->subject === $data['subject']
                    && $ticket->message === $data['message'] && $ticket->name === $data['name']
                    && $ticket->user_id === $user?->id, 409, 'Submission key was already used.');

                return $ticket;
            }

            $token = $user ? null : Str::random(64);
            $ticket = Contacts::query()->createOrFirst(['submission_key' => $key], [
                'name' => $data['name'], 'email' => $data['email'],
                'subject' => $data['subject'], 'message' => $data['message'],
                'user_id' => $user?->id, 'status' => 'open',
                'last_message_at' => now(),
                'guest_access_token' => $token,
                'guest_access_expires_at' => $token ? now()->addDays(30) : null,
            ]);
            if (! $ticket->wasRecentlyCreated) {
                abort_unless($ticket->email === $data['email'] && $ticket->subject === $data['subject']
                    && $ticket->message === $data['message'] && $ticket->name === $data['name']
                    && $ticket->user_id === $user?->id, 409, 'Submission key was already used.');

                return $ticket;
            }
            $message = $ticket->messages()->create([
                'sender_id' => $user?->id,
                'sender_type' => $user ? 'user' : 'guest',
                'message' => $data['message'],
            ]);
            $ticket->update(['user_read_message_id' => $message->id]);
            $created = true;

            return $ticket;
        });

        if ($created) {
            $this->notifyAfterCommit(config('services.support.email', 'scemorygmail@gmail.com'),
                "New support ticket #{$ticket->id}: {$ticket->subject}", 'A new support conversation is ready in the admin dashboard.');
            if (! $ticket->user_id) {
                $url = url('/en/contact?ticket='.$ticket->id.'&access='.$ticket->guest_access_token);
                $this->notifyAfterCommit($ticket->email, "Your support ticket #{$ticket->id}",
                    "Keep this private link to continue your conversation: {$url}");
            }
        }

        return $this->success([
            'ticket' => $this->ticketPayload($ticket),
            'access_token' => $ticket->user_id ? null : $ticket->guest_access_token,
        ], 'Contact Created Successfully');
    }

    public function myTickets(Request $request)
    {
        $query = Contacts::query()->where('user_id', $request->user()->id)
            ->with('latestMessage')
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', 'admin')
                ->whereRaw('contact_messages.id > COALESCE(contacts.user_read_message_id, 0)')]);

        return $this->success($query->orderByDesc('last_message_at')->orderByDesc('id')->paginate(15));
    }

    public function show(Request $request, int $id)
    {
        $ticket = Contacts::query()->findOrFail($id);
        $this->authorizeParticipant($request, $ticket);

        return $this->success($this->showPayload($ticket, $request->query('peek') !== '1'));
    }

    public function reply(Request $request, int $id)
    {
        $ticket = Contacts::query()->findOrFail($id);
        $this->authorizeParticipant($request, $ticket);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $message = $this->saveReply($ticket, $data, $request->user('sanctum')?->id, $ticket->user_id ? 'user' : 'guest');

        return $this->success($message);
    }

    public function adminTickets(Request $request)
    {
        $adminId = $request->user()->id;
        $query = Contacts::query()->with(['latestMessage', 'assignedAdmin:id,name'])
            ->withCount(['messages as unread_count' => function ($q) use ($adminId) {
                $q->whereIn('sender_type', ['user', 'guest'])
                    ->whereRaw('contact_messages.id > COALESCE((SELECT last_read_message_id FROM contact_admin_reads WHERE contact_id = contacts.id AND admin_id = ?), 0)', [$adminId]);
            }]);
        if (in_array($request->query('status'), ['open', 'pending', 'resolved'], true)) {
            $query->where('status', $request->query('status'));
        }
        if ($request->query('status') === 'unread') {
            $query->whereHas('messages', fn ($q) => $q->whereIn('sender_type', ['user', 'guest'])
                ->whereRaw('contact_messages.id > COALESCE((SELECT last_read_message_id FROM contact_admin_reads WHERE contact_id = contacts.id AND admin_id = ?), 0)', [$adminId]));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }
        $page = $query->orderByDesc('last_message_at')->orderByDesc('id')->paginate(15);
        $unreadTickets = Contacts::query()->whereHas('messages', fn ($q) => $q->whereIn('sender_type', ['user', 'guest'])
            ->whereRaw('contact_messages.id > COALESCE((SELECT last_read_message_id FROM contact_admin_reads WHERE contact_id = contacts.id AND admin_id = ?), 0)', [$adminId]))->count();

        return $this->success([
            'data' => $page->items(), 'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(), 'per_page' => $page->perPage(),
            'total' => $page->total(),
            'stats' => ['total' => Contacts::count(), 'unread' => $unreadTickets,
                'resolved' => Contacts::where('status', 'resolved')->count()],
        ]);
    }

    public function adminShow(Request $request, int $id)
    {
        return $this->success($this->showPayload(Contacts::query()->findOrFail($id), false, $request->user()->id));
    }

    public function adminReply(Request $request, int $id)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $ticket = Contacts::query()->findOrFail($id);
        $message = $this->saveReply($ticket, $data, $request->user()->id, 'admin');

        return $this->success($message);
    }

    public function adminStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'pending', 'resolved'])],
            'assigned_admin_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('role', 'admin')],
            'assign_to_me' => ['sometimes', 'boolean'],
        ]);
        $ticket = Contacts::query()->findOrFail($id);
        if ($data['assign_to_me'] ?? false) {
            $data['assigned_admin_id'] = $request->user()->id;
        }
        unset($data['assign_to_me']);
        $ticket->update($data);

        return $this->success($this->ticketPayload($ticket));
    }

    private function authorizeParticipant(Request $request, Contacts $ticket): void
    {
        if ($ticket->user_id) {
            abort_unless($request->user('sanctum')?->id === $ticket->user_id, 404);

            return;
        }
        $token = (string) $request->header('X-Support-Access', '');
        abort_unless($token !== '' && $ticket->guest_access_token
            && $ticket->guest_access_expires_at?->isFuture()
            && hash_equals($ticket->guest_access_token, $token), 404);
    }

    private function showPayload(Contacts $ticket, bool $participant, ?int $adminId = null): array
    {
        $messages = $ticket->messages()->orderByDesc('id')->paginate(30);
        $messages->setCollection($messages->getCollection()->reverse()->values());
        $unread = $ticket->messages()->where('sender_type', 'admin')->where('id', '>', $ticket->user_read_message_id ?? 0)->count();
        $visibleId = $messages->getCollection()->max('id') ?? 0;
        if ($participant) {
            $ticket->update(['user_read_message_id' => max($ticket->user_read_message_id ?? 0, $visibleId)]);
        } elseif ($adminId) {
            $previous = DB::table('contact_admin_reads')->where('contact_id', $ticket->id)->where('admin_id', $adminId)->value('last_read_message_id') ?? 0;
            DB::table('contact_admin_reads')->updateOrInsert(
                ['contact_id' => $ticket->id, 'admin_id' => $adminId],
                ['last_read_message_id' => max($previous, $visibleId)]
            );
        }

        return ['ticket' => [...$this->ticketPayload($ticket), 'unread_count' => $unread], 'messages' => $messages];
    }

    private function saveReply(Contacts $ticket, array $data, ?int $senderId, string $type)
    {
        $created = false;
        $message = DB::transaction(function () use ($ticket, $data, $senderId, $type, &$created) {
            $locked = Contacts::query()->lockForUpdate()->findOrFail($ticket->id);
            $existing = $locked->messages()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->message === $data['message'] && $existing->sender_id === $senderId
                    && $existing->sender_type === $type, 409, 'Message key was already used.');

                return $existing;
            }
            $message = $locked->messages()->create([
                'sender_id' => $senderId, 'sender_type' => $type,
                'message' => $data['message'], 'idempotency_key' => $data['idempotency_key'],
            ]);
            $locked->update(['status' => $type === 'admin' ? 'pending' : 'open', 'last_message_at' => now()]);
            if ($type === 'admin' && ! $locked->user_id && ! $locked->guest_access_token) {
                $locked->update(['guest_access_token' => Str::random(64)]);
            }
            if ($type === 'admin' && ! $locked->user_id) {
                $locked->update(['guest_access_expires_at' => now()->addDays(30)]);
            }
            $created = true;

            return $message;
        });
        if ($created) {
            if ($type === 'admin') {
                $ticket->refresh();
                $url = $ticket->user_id ? url('/en/contact?ticket='.$ticket->id)
                    : url('/en/contact?ticket='.$ticket->id.'&access='.$ticket->guest_access_token);
                $this->notifyAfterCommit($ticket->email, "Support reply #{$ticket->id}: {$ticket->subject}", "You have a new support reply. Open your conversation: {$url}");
            } else {
                $this->notifyAfterCommit(config('services.support.email', 'scemorygmail@gmail.com'),
                    "Support update #{$ticket->id}: {$ticket->subject}", 'A customer replied in the support dashboard.');
            }
        }

        return $message;
    }

    private function notifyAfterCommit(string $email, string $subject, string $body): void
    {
        try {
            Mail::to($email)->queue(new SupportNotification($subject, $body));
        } catch (\Throwable $exception) {
            Log::warning('Support notification could not be queued', ['exception' => $exception::class]);
        }
    }

    private function ticketPayload(Contacts $ticket): array
    {
        return $ticket->only(['id', 'user_id', 'name', 'email', 'subject', 'status',
            'assigned_admin_id', 'last_message_at', 'created_at']);
    }
}
