<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EmailLogController extends Controller
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Display a listing of email delivery logs with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmailLog::query()->with('client');

        // Search filter
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('template_name', 'like', "%{$search}%")
                  ->orWhere('error_message', 'like', "%{$search}%")
                  ->orWhere('resend_message_id', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search)
                      ->orWhere('submission_id', (int) $search)
                      ->orWhere('client_id', (int) $search);
                }
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Template filter
        if ($template = $request->input('template')) {
            if ($template !== 'all') {
                $query->where('template_name', $template);
            }
        }

        // Date range filter
        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Overall summary counts (unfiltered or base stats)
        $summary = [
            'total' => EmailLog::count(),
            'sent' => EmailLog::where('status', 'sent')->count(),
            'failed' => EmailLog::where('status', 'failed')->count(),
            'pending' => EmailLog::whereIn('status', ['pending', 'queued'])->count(),
        ];

        $perPage = min(max((int) $request->input('per_page', 25), 5), 100);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'summary' => $summary,
            'logs' => $logs,
        ]);
    }

    /**
     * Display details of a single email log.
     */
    public function show($id): JsonResponse
    {
        $log = EmailLog::with('client')->findOrFail($id);

        return response()->json([
            'log' => $log,
        ]);
    }

    /**
     * Resend an email from its log record.
     */
    public function resend(Request $request, $id): JsonResponse
    {
        $log = EmailLog::findOrFail($id);

        $success = $this->emailService->retry($log);
        $log->refresh();

        if ($success) {
            return response()->json([
                'message' => 'Email resent successfully.',
                'log' => $log,
            ]);
        }

        return response()->json([
            'message' => 'Failed to resend email: ' . ($log->error_message ?? 'Unknown error'),
            'log' => $log,
        ], 500);
    }
}
