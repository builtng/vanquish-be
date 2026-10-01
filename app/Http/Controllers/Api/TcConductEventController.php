<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TcConductEvent;
use App\Models\TrainingCounsellor;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TcConductEventController extends Controller
{
    /**
     * List conduct events logged for a trainee counsellor, for the admin
     * profile's Progress Overview panel.
     */
    public function index($tc)
    {
        $trainingCounsellor = TrainingCounsellor::where('uuid', $tc)->orWhere('tc_id', $tc)->firstOrFail();

        $events = $trainingCounsellor->conductEvents()->with('loggedBy:id,name')->get();

        return response()->json([
            'events' => $events,
            'summary' => $this->summarize($events),
            'types' => $this->typesForFrontend(),
        ]);
    }

    /**
     * Log a conduct event against a trainee counsellor: records it (for the
     * progress bar) and emails the trainee counsellor a notice.
     */
    public function store(Request $request, $tc, EmailService $emailService)
    {
        $trainingCounsellor = TrainingCounsellor::where('uuid', $tc)->orWhere('tc_id', $tc)->firstOrFail();

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(TcConductEvent::TYPES)),
            'notes' => 'nullable|string|max:2000',
        ]);

        $config = TcConductEvent::TYPES[$validated['type']];

        $event = $trainingCounsellor->conductEvents()->create([
            'type' => $validated['type'],
            'notes' => $validated['notes'] ?? null,
            'logged_by_user_id' => $request->user()->id,
        ]);

        $emailSent = false;
        if ($trainingCounsellor->email) {
            $emailSent = $emailService->sendAndLog(
                $trainingCounsellor,
                $config['email_template'],
                [
                    'tc_name' => $trainingCounsellor->name,
                    'event_label' => $config['label'],
                    'notes' => $validated['notes'] ?: 'N/A',
                    'logged_date' => now()->format('d M Y, H:i'),
                ]
            );
        }

        $event->update(['email_sent' => $emailSent]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'tc_conduct_event_logged',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $trainingCounsellor->id,
            'description' => "{$config['label']} logged for {$trainingCounsellor->name}" . ($validated['notes'] ? ": {$validated['notes']}" : ''),
            'ip_address' => $request->ip(),
        ]);

        return response()->json($event->load('loggedBy:id,name'), 201);
    }

    /**
     * The authenticated trainee counsellor's own conduct events, for their
     * portal progress bar.
     */
    public function myEvents(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized. Counsellor access required.'], 403);
        }

        $trainingCounsellor = TrainingCounsellor::findOrFail($user->training_counsellor_id);
        $events = $trainingCounsellor->conductEvents()->get();

        return response()->json([
            'events' => $events,
            'summary' => $this->summarize($events),
            'types' => $this->typesForFrontend(),
        ]);
    }

    private function summarize(Collection $events): array
    {
        $counts = array_fill_keys(array_keys(TcConductEvent::TYPES), 0);

        foreach ($events as $event) {
            if (array_key_exists($event->type, $counts)) {
                $counts[$event->type]++;
            }
        }

        $total = array_sum($counts);

        return [
            'counts' => $counts,
            'total' => $total,
            // Simple reliability score: -5 points per incident, floor 0.
            'reliability_score' => max(0, 100 - ($total * 5)),
            'last_event_at' => $events->max('created_at'),
        ];
    }

    private function typesForFrontend(): array
    {
        return collect(TcConductEvent::TYPES)
            ->map(fn ($config, $key) => [
                'value' => $key,
                'label' => $config['label'],
                'description' => $config['description'],
            ])
            ->values()
            ->toArray();
    }
}
