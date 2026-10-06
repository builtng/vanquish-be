<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\TrainingCounsellorController;
use App\Http\Controllers\Api\TrainingCounsellorHolidayController;
use App\Http\Controllers\Api\TcConductEventController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\IntakeFormController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\InductionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ClientBookingController;
use App\Http\Controllers\Api\JotFormWebhookController;
use App\Http\Controllers\Api\HireVireWebhookController;
use App\Http\Controllers\Api\TrafftWebhookController;
use App\Http\Controllers\Api\ClientAgreementController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\IntakeController;
use App\Http\Controllers\Api\TraineeApplicationController;
use App\Http\Controllers\StaffNoteController;
use App\Http\Controllers\Api\MenuPrivilegeController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CompanySettingsController;
use App\Http\Controllers\Api\PsgReflectionController;
use App\Http\Controllers\Api\PsgSessionController;
use App\Http\Controllers\Api\SharedDocumentController;
use App\Http\Controllers\Api\SessionNoteController;
use App\Http\Controllers\Api\MatchingAlgorithmSettingController;
use App\Http\Controllers\Api\QcApplicationController;
use Illuminate\Support\Facades\Route;

// Public routes with stricter rate limiting
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('maintenance');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Service routes (public)
Route::get('/services/coaching-capacity', [\App\Http\Controllers\Api\ServiceController::class, 'checkCoachingCapacity']);
Route::get('/maintenance', [\App\Http\Controllers\Api\ServiceController::class, 'checkMaintenance']);
Route::post('/coupons/verify', [\App\Http\Controllers\Api\CouponController::class, 'verify']);

// Company settings (public read)
Route::get('/company-settings', [CompanySettingsController::class, 'index']);

// Service routes (public)
Route::get('/services/all', [ServiceController::class, 'getAllServices']);

// Client booking routes (public - clients book without auth)
Route::prefix('client-booking')->group(function () {
    Route::post('/authenticate', [ClientBookingController::class, 'authenticate']);
    Route::get('/{clientUuid}/status', [ClientBookingController::class, 'getBookingStatus']);
    Route::get('/{clientUuid}/available-slots', [ClientBookingController::class, 'getAvailableSlots']);
    Route::get('/{clientUuid}/next-block-slots', [ClientBookingController::class, 'getNextBlockSlots']);
    Route::get('/{clientUuid}/available-counsellors', [ClientBookingController::class, 'getAvailableCounsellors']);
    Route::post('/{clientUuid}/choose-counsellor', [ClientBookingController::class, 'chooseCounsellor']);
    Route::post('/book-session', [ClientBookingController::class, 'bookSession']);
    Route::post('/book-block', [ClientBookingController::class, 'bookBlock']);
});

// Client Consultation Slots Booking (public - uses uuid)
Route::prefix('client')->group(function () {
    Route::post('/book-consultation', [\App\Http\Controllers\Api\ClientConsultationSlotController::class, 'bookConsultation']);
});
Route::get('/consultation-slots/available', [\App\Http\Controllers\Api\ClientConsultationSlotController::class, 'getAvailableSlots']);

// Mid Range / Coaching & Counselling intake (public - new, additive form)
Route::post('/mid-range-intake/filtered-counsellors', [\App\Http\Controllers\Api\MidRangeIntakeController::class, 'filteredCounsellors']);
Route::get('/mid-range-intake/consultation-availability/{tcUuid}', [\App\Http\Controllers\Api\MidRangeIntakeController::class, 'consultationAvailability']);

// Payment routes (public for client intake)
Route::post('/payments/create-intent', [PaymentController::class, 'createPaymentIntent']);
Route::post('/payments/confirm', [PaymentController::class, 'confirmPayment']);
// Webhook endpoint - no rate limiting but signature verification required
Route::post('/payments/webhook', [PaymentController::class, 'handleWebhook'])->withoutMiddleware(['throttle:api']);

// JotForm webhooks (public - no auth required)
Route::post('/jotform/agreement-webhook', [JotFormWebhookController::class, 'handleAgreementSubmission'])->withoutMiddleware(['throttle:api']);
Route::post('/jotform/intake-webhook', [JotFormWebhookController::class, 'handleIntakeSubmission'])->withoutMiddleware(['throttle:api']);
Route::post('/jotform/webhook/trainee', [JotFormWebhookController::class, 'handleTraineeSubmission'])->withoutMiddleware(['throttle:api']);

// HireVire webhook (Stage 2 video completion)
Route::post('/hirevire/webhook', [HireVireWebhookController::class, 'handleSubmission'])->withoutMiddleware(['throttle:api']);

// Trafft webhook (Stage 3 interview booking)
Route::post('/trafft/webhook', [TrafftWebhookController::class, 'handleBooking'])->withoutMiddleware(['throttle:api']);

// Public PSG Session routes
Route::get('/public/psg/{token}', [PsgSessionController::class, 'getForm'])->withoutMiddleware(['throttle:api']);
Route::post('/public/psg/{token}', [PsgSessionController::class, 'submitSession'])->withoutMiddleware(['throttle:api']);

// Intake Forms (public - clients need to submit without auth)
// Rate limited to prevent abuse
Route::middleware(['throttle:10,1', 'maintenance'])->group(function () {
    Route::post('/client-intake', [IntakeFormController::class, 'clientIntake']);
    Route::post('/intake/confirm-payment', [IntakeController::class, 'handlePaymentSuccess']);
    Route::get('/intake/success', [IntakeController::class, 'success'])->name('intake.success');
    Route::post('/tc-intake', [IntakeFormController::class, 'tcIntake']);
    Route::get('/client-agreement/prefill/{uuid}', [ClientAgreementController::class, 'getAgreementData']);
    Route::post('/client-agreement/submit', [ClientAgreementController::class, 'submitAgreement']);
    Route::get('/client-agreement/download/{uuid}', [ClientAgreementController::class, 'downloadAgreementPdf']);
    Route::post('/trainee-application/submit', [TraineeApplicationController::class, 'store']);
    Route::post('/trainee-applications', [TraineeApplicationController::class, 'store']);
    Route::post('/trainee-application/stage-two-submission', [TraineeApplicationController::class, 'handleStageTwoSubmission']);
    Route::get('/trainee-interview/slots', [TraineeApplicationController::class, 'getAvailableInterviewSlots']);
    Route::post('/trainee-interview/book', [TraineeApplicationController::class, 'bookInterview']);
    Route::post('/qualified-counsellor/submit', [TrainingCounsellorController::class, 'submitQualifiedForm']);
    Route::post('/submit-qualified-form', [TrainingCounsellorController::class, 'submitQualifiedForm']);
    Route::post('/qualified-counsellor/upload-document', [TrainingCounsellorController::class, 'uploadDocument']);
    Route::get('/qualified-counsellor/prefill/{uuid}', [TrainingCounsellorController::class, 'publicPrefill']);
});

// Induction acceptance (public - TCs need to accept without auth)
Route::post('/induction/accept/{token}', [InductionController::class, 'acceptInvitation']);
Route::post('/induction/decline/{token}', [InductionController::class, 'declineInvitation']);

// Placement acceptance response (public - candidates respond without auth)
Route::get('/trainee-placement-response/{token}', [TraineeApplicationController::class, 'getPlacementResponse']);
Route::post('/trainee-placement-response/{token}', [TraineeApplicationController::class, 'submitPlacementResponse']);

// Protected routes - higher rate limit for authenticated users
Route::middleware(['auth:sanctum', 'throttle:200,1'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::delete('/user/account', [AuthController::class, 'deleteAccount']);

    // 2FA routes
    Route::post('/2fa/initiate', [AuthController::class, 'initiate2FASetup']);
    Route::post('/2fa/verify', [AuthController::class, 'verify2FASetup']);
    Route::post('/2fa/disable', [AuthController::class, 'disable2FA']);
    Route::post('/2fa/regenerate', [AuthController::class, 'regenerate2FACode']);

    // Clients
    Route::middleware('staff')->group(function () {
        Route::apiResource('clients', ClientController::class)->except(['show']);
    });

    // Client view access (Staff, Admin, or Assigned Counsellor)
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::get('/clients/{client}/details', [ClientController::class, 'details']);
    Route::get('/clients/{client}/download-report', [ClientController::class, 'downloadReport']);

    // Protected client actions
    Route::middleware('staff')->group(function () {
        Route::post('/clients/{client}/progress-stage', [ClientController::class, 'progressStage']);
        Route::post('/clients/{client}/send-email', [ClientController::class, 'sendEmail']);
        Route::post('/clients/{client}/send-feedback-form', [ClientController::class, 'sendFeedbackForm']);
        Route::post('/clients/{client}/resend-agreement', [ClientController::class, 'resendAgreement']);
        Route::post('/clients/{client}/ready-for-counsellor-selection', [ClientController::class, 'markReadyForCounsellorSelection']);
        Route::post('/clients/{client}/update-satisfaction', [ClientController::class, 'updateSatisfactionScore']);
        Route::get('/clients/eligible-for-feedback', [ClientController::class, 'getEligibleForFeedback']);
    });

    // Client photo upload (admin only)
    Route::middleware('admin')->group(function () {
        Route::post('/clients/{client}/upload-photo', [ClientController::class, 'uploadPhoto']);
        Route::delete('/clients/{client}/delete-photo', [ClientController::class, 'deletePhoto']);
    });

    // Trainee Counsellors (staff and admin only)
    Route::middleware('staff')->group(function () {
        Route::apiResource('training-counsellors', TrainingCounsellorController::class);
        Route::get('/training-counsellors/{tc}/details', [TrainingCounsellorController::class, 'details']);
        Route::get('/training-counsellors/{tc}/download-report', [TrainingCounsellorController::class, 'downloadReport']);
        Route::post('/training-counsellors/{tc}/transition-to-qualified', [TrainingCounsellorController::class, 'transitionToQualified']);
        Route::post('/training-counsellors/{tc}/send-email', [TrainingCounsellorController::class, 'sendEmail']);
        Route::post('/training-counsellors/{tc}/portal-invite', [TrainingCounsellorController::class, 'sendPortalInvite']);
        Route::post('/training-counsellors/bulk-portal-invite', [TrainingCounsellorController::class, 'bulkPortalInvite']);
        Route::get('/training-counsellors/{tc}/holidays', [TrainingCounsellorHolidayController::class, 'index']);
        Route::post('/training-counsellors/{tc}/holidays', [TrainingCounsellorHolidayController::class, 'store']);
        Route::delete('/training-counsellors/{tc}/holidays/{holiday}', [TrainingCounsellorHolidayController::class, 'destroy']);
        Route::get('/training-counsellors/{tc}/caseload', [TrainingCounsellorController::class, 'caseload']);
        Route::get('/training-counsellors/{tc}/slot-bookings', [TrainingCounsellorController::class, 'getSlotBookings']);
        Route::get('/training-counsellors/{tc}/document/{field}', [TrainingCounsellorController::class, 'downloadDocument']);
        Route::get('/training-counsellor-holidays/pending', [TrainingCounsellorHolidayController::class, 'pending']);
        Route::patch('/training-counsellor-holidays/{holiday}/approve', [TrainingCounsellorHolidayController::class, 'approve']);
        Route::patch('/training-counsellor-holidays/{holiday}/reject', [TrainingCounsellorHolidayController::class, 'reject']);
        Route::get('/training-counsellors/{tc}/conduct-events', [TcConductEventController::class, 'index']);
        Route::post('/training-counsellors/{tc}/conduct-events', [TcConductEventController::class, 'store']);
    });

    // Counsellor portal endpoints (counsellors can access their own data)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/counsellor/my-data', [TrainingCounsellorController::class, 'getOwnData']);
        Route::post('/counsellor/upload-photo', [TrainingCounsellorController::class, 'uploadOwnPhoto']);
        Route::put('/counsellor/consultation-availability-toggles', [TrainingCounsellorController::class, 'updateConsultationDelegationPreferences']);
        Route::delete('/counsellor/delete-photo', [TrainingCounsellorController::class, 'deleteOwnPhoto']);
        Route::get('/counsellor/psg-sessions', [PsgSessionController::class, 'mySessions']);
        Route::get('/counsellor/holidays', [TrainingCounsellorHolidayController::class, 'myHolidays']);
        Route::post('/counsellor/holidays', [TrainingCounsellorHolidayController::class, 'requestHoliday']);
        Route::get('/counsellor/conduct-events', [TcConductEventController::class, 'myEvents']);
    });

    // TC photo upload (admin and owning counsellor)
    Route::post('/training-counsellors/{tc}/upload-photo', [TrainingCounsellorController::class, 'uploadPhoto']);
    Route::delete('/training-counsellors/{tc}/delete-photo', [TrainingCounsellorController::class, 'deletePhoto']);

    // Qualified Counsellor Form staff triggers
    Route::middleware('staff')->group(function () {
        Route::post('/training-counsellors/{tc}/send-qualified-form-email', [TrainingCounsellorController::class, 'sendQualifiedFormEmail']);
        Route::get('/qc-applications', [QcApplicationController::class, 'index']);
        Route::get('/qc-applications/{id}', [QcApplicationController::class, 'show']);
        Route::post('/qc-applications/{id}/accept', [QcApplicationController::class, 'accept']);
        Route::post('/qc-applications/{id}/link', [QcApplicationController::class, 'link']);
        Route::post('/qc-applications/{id}/reject', [QcApplicationController::class, 'reject']);
        Route::post('/qc-applications/{id}/restore', [QcApplicationController::class, 'restore']);
    });

    // Consultations (staff and admin only)
    Route::middleware('staff')->group(function () {
        Route::get('/consultation-stats', [ConsultationController::class, 'stats']);
        Route::apiResource('consultations', ConsultationController::class);
        Route::post('/consultations/{consultation}/complete', [ConsultationController::class, 'complete']);
        Route::post('/consultations/{consultation}/cancel', [ConsultationController::class, 'cancel']);
        Route::post('/consultations/{consultation}/reschedule', [ConsultationController::class, 'reschedule']);
    });

    // Pending Matches (staff and admin only)
    Route::middleware('staff')->group(function () {
        Route::get('/pending-matches', [ClientController::class, 'pendingMatches']);
        Route::get('/pending-matches/count', [ClientController::class, 'pendingMatchesCount']);
        Route::post('/matches', [ClientController::class, 'assignMatch']);
        Route::post('/unassign-match', [ClientController::class, 'unassignMatch']);
        Route::get('/matching-algorithm-settings', [MatchingAlgorithmSettingController::class, 'index']);
    });

    // Activity Logs (staff and admin only)
    Route::middleware('staff')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::post('/activity-logs', [ActivityLogController::class, 'store']);
        Route::put('/activity-logs/{id}', [ActivityLogController::class, 'update']);
        Route::delete('/activity-logs/{id}', [ActivityLogController::class, 'destroy']);
    });

    // Service Management (admin only)
    Route::middleware('admin')->group(function () {
        Route::post('/services/update-capacity', [ServiceController::class, 'updateCapacity']);
        Route::post('/services/update-price', [ServiceController::class, 'updatePrice']);
        Route::post('/services/update-maintenance', [ServiceController::class, 'updateMaintenance']);

        // Coupons
        Route::apiResource('coupons', \App\Http\Controllers\Api\CouponController::class);

        // Admin Consultation Slots & Days Off
        Route::post('/consultation-slots/preview', [\App\Http\Controllers\Api\Admin\ConsultationSlotController::class, 'previewRecurring']);
        Route::post('/consultation-slots/recurring', [\App\Http\Controllers\Api\Admin\ConsultationSlotController::class, 'storeRecurring']);
        Route::post('/consultation-slots/bulk', [\App\Http\Controllers\Api\Admin\ConsultationSlotController::class, 'storeBulk']);
        Route::post('/consultation-slots/range', [\App\Http\Controllers\Api\Admin\ConsultationSlotController::class, 'storeRange']);
        Route::apiResource('consultation-slots', \App\Http\Controllers\Api\Admin\ConsultationSlotController::class);

        Route::get('/consultation-days-off', [\App\Http\Controllers\Api\Admin\ConsultationDayOffController::class, 'index']);
        Route::post('/consultation-days-off', [\App\Http\Controllers\Api\Admin\ConsultationDayOffController::class, 'store']);
        Route::delete('/consultation-days-off/{id}', [\App\Http\Controllers\Api\Admin\ConsultationDayOffController::class, 'destroy']);
    });

    // User Management (admin only)
    Route::middleware('admin')->group(function () {
        // Email Templates
        Route::get('/email-templates', [EmailTemplateController::class, 'index']);
        Route::get('/email-templates/{type}', [EmailTemplateController::class, 'show']);
        Route::put('/email-templates/{type}', [EmailTemplateController::class, 'update']);
        Route::post('/email-templates/{type}/reset', [EmailTemplateController::class, 'reset']);

        // Email Delivery Logs
        Route::get('/email-logs', [\App\Http\Controllers\Api\EmailLogController::class, 'index']);
        Route::get('/email-logs/{id}', [\App\Http\Controllers\Api\EmailLogController::class, 'show']);
        Route::post('/email-logs/{id}/resend', [\App\Http\Controllers\Api\EmailLogController::class, 'resend']);

        // Email Sender Settings
        Route::prefix('admin')->group(function () {
            Route::apiResource('email-senders', \App\Http\Controllers\Admin\EmailSenderSettingController::class)->except(['show']);
        });

        Route::apiResource('users', UserController::class);
        Route::put('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::get('/users-count', [UserController::class, 'count']);

        // Menu Privileges
        Route::get('/menu-privileges', [\App\Http\Controllers\Api\MenuPrivilegeController::class, 'index']);
        Route::put('/menu-privileges', [\App\Http\Controllers\Api\MenuPrivilegeController::class, 'update']);

        // Matching Algorithm Settings
        Route::put('/matching-algorithm-settings', [MatchingAlgorithmSettingController::class, 'update']);

        // Company Branding Settings
        Route::put('/company-settings', [CompanySettingsController::class, 'update']);
        Route::post('/company-settings/logo', [CompanySettingsController::class, 'uploadLogo']);
        Route::delete('/company-settings/logo', [CompanySettingsController::class, 'deleteLogo']);

        // 4-Way Agreement Document Management
        Route::get('/company-settings/four-way-agreement/status', [CompanySettingsController::class, 'getFourWayAgreementStatus']);
        Route::post('/company-settings/four-way-agreement', [CompanySettingsController::class, 'uploadFourWayAgreement']);
        Route::delete('/company-settings/four-way-agreement', [CompanySettingsController::class, 'deleteFourWayAgreement']);

    });
    
    // Trainee Applications (staff and admin)
    Route::middleware('staff')->group(function () {
        Route::get('/trainee-applications', [TraineeApplicationController::class, 'index']);
        Route::get('/trainee-applications/count', [TraineeApplicationController::class, 'pendingCount']);
        Route::get('/trainee-applications/{trainee_application}', [TraineeApplicationController::class, 'show']);
        Route::post('/trainee-applications/send-invite-email', [TraineeApplicationController::class, 'inviteEmail']);
        Route::post('/trainee-applications/{trainee_application}/status', [TraineeApplicationController::class, 'updateStatus']);
        Route::post('/trainee-applications/{trainee_application}/invite', [TraineeApplicationController::class, 'sendInvite']);
        Route::post('/trainee-applications/{trainee_application}/invite-stage-three', [TraineeApplicationController::class, 'sendStageThreeInvite']);
        Route::post('/trainee-applications/{trainee_application}/attendance', [TraineeApplicationController::class, 'recordAttendance']);
        Route::post('/trainee-applications/{trainee_application}/decision', [TraineeApplicationController::class, 'makeDecision']);
        // Stage 4 Onboarding
        Route::post('/trainee-applications/{trainee_application}/paperwork', [TraineeApplicationController::class, 'updatePaperwork']);
        Route::post('/trainee-applications/{trainee_application}/induction-attendance', [TraineeApplicationController::class, 'recordInductionAttendance']);
        Route::post('/trainee-applications/{trainee_application}/portal-invite', [TraineeApplicationController::class, 'sendPortalInvite']);
        Route::post('/trainee-applications/{trainee_application}/finalize', [TraineeApplicationController::class, 'finalizePlacement']);
        Route::get('/trainee-applications/{id}/zoom-signature', [TraineeApplicationController::class, 'getZoomSignature']);
    });

    // Trainee Applications (admin only)
    Route::middleware('admin')->group(function () {
        Route::delete('/trainee-applications/{trainee_application}', [TraineeApplicationController::class, 'destroy']);
        Route::get('/trainee-applications-settings', [TraineeApplicationController::class, 'getSettings']);
        Route::post('/trainee-applications-settings', [TraineeApplicationController::class, 'updateSettings']);
    });

    // List of users for notes (accessible by any auth user, used by staff notes)
    Route::get('/users-list', [UserController::class, 'publicList']);

    // Menu Privileges - available to all authenticated users (to show correct sidebar)
    Route::get('/menu-privileges/for-role/{role}', [\App\Http\Controllers\Api\MenuPrivilegeController::class, 'getForRole']);

    // Inductions (staff and admin only)
    Route::middleware('staff')->group(function () {
        Route::apiResource('inductions', InductionController::class);
        Route::post('/inductions/{id}/add-attendees', [InductionController::class, 'addAttendees']);
    });

    // Messages (all authenticated users)
    Route::prefix('messages')->group(function () {
        Route::get('/', [MessageController::class, 'index']);
        Route::get('/conversations', [MessageController::class, 'conversations']);
        Route::get('/unread-count', [MessageController::class, 'unreadCount']);
        Route::get('/staff-list', [MessageController::class, 'getStaffList']);
        Route::get('/{id}', [MessageController::class, 'show']);
        Route::post('/{id}/mark-read', [MessageController::class, 'markAsRead']);
        Route::post('/{id}/trash', [MessageController::class, 'trash']);
        Route::post('/{id}/restore', [MessageController::class, 'restore']);
        Route::delete('/{id}', [MessageController::class, 'destroy']);

        // Staff/admin can send to counsellors
        Route::middleware('staff')->group(function () {
            Route::post('/send-to-counsellor', [MessageController::class, 'sendToCounsellor']);
        });

        // Counsellors can send to staff
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/send-to-staff', [MessageController::class, 'sendToStaff']);
        });

        Route::post('/conversations/{type}/{id}/mark-read', [MessageController::class, 'markConversationAsRead']);
        Route::delete('/conversations/{type}/{id}', [MessageController::class, 'deleteConversation']);
    });

    // Shared Documents & Folders
    Route::get('/shared-documents', [SharedDocumentController::class, 'index']);
    Route::get('/shared-documents/{document}', [SharedDocumentController::class, 'showDocument']);
    Route::get('/folders', [SharedDocumentController::class, 'index']);
    Route::get('/folders/{folder}', [SharedDocumentController::class, 'show']);

    Route::get('/contacts/{type}/{id}/files', [SharedDocumentController::class, 'contactFiles']);
    Route::post('/shared-documents', [SharedDocumentController::class, 'store']);
    Route::get('/shared-documents/{document}/download', [SharedDocumentController::class, 'download']);
    
    Route::middleware('staff')->group(function () {
        Route::post('/folders', [SharedDocumentController::class, 'createFolder']);
        Route::patch('/folders/{folder}', [SharedDocumentController::class, 'updateFolder']);
        Route::post('/folders/{folder}/share', [SharedDocumentController::class, 'shareFolder']);
        Route::delete('/folders/{folder}', [SharedDocumentController::class, 'destroyFolder']);
        
        Route::patch('/shared-documents/{document}', [SharedDocumentController::class, 'update']);
        Route::delete('/shared-documents/{document}', [SharedDocumentController::class, 'destroy']);
    });

    // PSG Reflections
    Route::get('/psg-reflections', [PsgReflectionController::class, 'index']);
    Route::post('/psg-reflections', [PsgReflectionController::class, 'store']);

    // Attendance Groups (PSG) - CRUD for staff/admin
    Route::middleware('staff')->group(function () {
        Route::get('/attendance-groups', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'index']);
        Route::post('/attendance-groups', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'store']);
        Route::get('/attendance-groups/unassigned', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'unassigned']);
        Route::get('/attendance-groups/{attendanceGroup}', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'show']);
        Route::patch('/attendance-groups/{attendanceGroup}', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'update']);
        Route::delete('/attendance-groups/{attendanceGroup}', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'destroy']);
        Route::post('/attendance-groups/{attendanceGroup}/allocate', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'allocate']);
        Route::post('/attendance-groups/{attendanceGroup}/deallocate', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'deallocate']);
        Route::patch('/attendance-groups/{attendanceGroup}/toggle-status', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'toggleStatus']);
        Route::get('/attendance-groups/{attendanceGroup}/psg-logs', [PsgSessionController::class, 'groupLogs']);
        Route::get('/attendance-groups/{attendanceGroup}/discussions', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'discussions']);
        Route::post('/attendance-groups/{attendanceGroup}/discussions', [\App\Http\Controllers\Api\AttendanceGroupController::class, 'postDiscussion']);
    });

    // Session Notes
    Route::get('/session-notes', [SessionNoteController::class, 'index']);
    Route::post('/session-notes', [SessionNoteController::class, 'store']);
    Route::get('/session-notes/{id}', [SessionNoteController::class, 'show']);

    // Staff Notes (staff and admin only)
    Route::middleware('staff')->group(function () {
        Route::prefix('staff-notes')->group(function () {
            Route::get('/', [StaffNoteController::class, 'index']);
            Route::post('/', [StaffNoteController::class, 'store']);
            Route::get('/unread', [StaffNoteController::class, 'getUnreadNotes']);
            Route::post('/{id}/read', [StaffNoteController::class, 'markAsRead']);
        });
    });

    // Broadcasting auth (for private channels via Laravel Echo)
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });
});
