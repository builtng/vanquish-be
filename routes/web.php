<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrations-secret', function (\Illuminate\Http\Request $request) {
    if ($request->query('token') !== 'vqt_secret_migrate_2026') {
        return response('Unauthorized', 401);
    }
    try {
        Artisan::call('migrate', ['--force' => true]);
        return 'Migrations run successfully: <br><pre>' . Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Error running migrations: ' . $e->getMessage();
    }
});

Route::get('/q02-inspect-secret', function (\Illuminate\Http\Request $request) {
    if ($request->query('token') !== 'vqt_secret_migrate_2026') {
        return response('Unauthorized', 401);
    }

    $tc = \App\Models\TrainingCounsellor::withTrashed()->where('tc_id', 'QC002')->first();

    $results = [];
    if ($tc) {
        $results['tc'] = [
            'id' => $tc->id,
            'tc_id' => $tc->tc_id,
            'name' => $tc->name,
            'legal_first_name' => $tc->legal_first_name,
            'legal_last_name' => $tc->legal_last_name,
            'email' => $tc->email,
            'phone' => $tc->phone,
            'counsellor_type' => $tc->counsellor_type,
            'status' => $tc->status,
            'joined_date' => $tc->joined_date,
            'created_at' => $tc->created_at,
            'updated_at' => $tc->updated_at,
            'deleted_at' => $tc->deleted_at,
            'archived_at' => $tc->archived_at,
            'registered_address' => $tc->registered_address,
            'registered_city' => $tc->registered_city,
            'registered_postcode' => $tc->registered_postcode,
            'has_supervisor' => $tc->has_supervisor,
            'previous_vanquish_work' => $tc->previous_vanquish_work,
            'areas_to_improve' => $tc->areas_to_improve,
            'unique_trait' => $tc->unique_trait,
            'counsellor_training_details' => $tc->counsellor_training_details,
            'qualified_to_work_with' => $tc->qualified_to_work_with,
            'challenging_cases' => $tc->challenging_cases,
            'signature' => $tc->signature,
            'signature_date' => $tc->signature_date,
            'qualified_form_completed' => $tc->qualified_form_completed,
            'has_qualification_document' => !empty($tc->qualification_document),
            'has_dbs_certificate_qualified' => !empty($tc->dbs_certificate_qualified),
            'has_insurance_qualified' => !empty($tc->insurance_qualified),
            'has_self_employment_proof' => !empty($tc->self_employment_proof),
            'has_professional_membership' => !empty($tc->professional_membership),
        ];

        $qcApps = \App\Models\QcApplication::where('training_counsellor_id', $tc->id)
            ->orWhere('suggested_training_counsellor_id', $tc->id)
            ->orWhere('email', $tc->email)
            ->get();

        $results['qc_applications'] = $qcApps->map(function ($app) {
            return [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'person_id' => $app->person_id,
                'training_counsellor_id' => $app->training_counsellor_id,
                'suggested_training_counsellor_id' => $app->suggested_training_counsellor_id,
                'legal_first_name' => $app->legal_first_name,
                'legal_last_name' => $app->legal_last_name,
                'name' => $app->name,
                'email' => $app->email,
                'phone' => $app->phone,
                'status' => $app->status,
                'created_at' => $app->created_at,
                'updated_at' => $app->updated_at,
                'archived_at' => $app->archived_at,
            ];
        });

        $logs = \App\Models\ActivityLog::where(function ($q) use ($tc) {
                $q->where('model_type', \App\Models\TrainingCounsellor::class)
                  ->where('model_id', $tc->id);
            })
            ->orWhere('description', 'LIKE', '%QC002%')
            ->orWhere('description', 'LIKE', '%' . $tc->email . '%')
            ->orderBy('id', 'desc')
            ->get();

        $results['activity_logs'] = $logs->map(function ($log) {
            $changes = $log->changes;
            if (is_array($changes)) {
                foreach (['qualification_document', 'dbs_certificate_qualified', 'insurance_qualified', 'self_employment_proof', 'professional_membership'] as $docKey) {
                    if (isset($changes[$docKey])) {
                        $changes[$docKey] = '[DOCUMENT_CONTENT_OMITTED]';
                    }
                    if (isset($changes['attributes'][$docKey])) {
                        $changes['attributes'][$docKey] = '[DOCUMENT_CONTENT_OMITTED]';
                    }
                    if (isset($changes['old'][$docKey])) {
                        $changes['old'][$docKey] = '[DOCUMENT_CONTENT_OMITTED]';
                    }
                }
            }
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'model_type' => $log->model_type,
                'model_id' => $log->model_id,
                'description' => $log->description,
                'changes' => $changes,
                'created_at' => $log->created_at,
            ];
        });
    } else {
        $results['tc'] = null;
        $results['all_qc'] = \App\Models\TrainingCounsellor::withTrashed()
            ->where('tc_id', 'LIKE', '%QC%')
            ->orWhere('name', 'LIKE', '%Dettol%')
            ->orWhere('name', 'LIKE', '%Rooshan%')
            ->get(['id', 'tc_id', 'name', 'legal_first_name', 'legal_last_name', 'email', 'status', 'created_at', 'updated_at', 'deleted_at', 'archived_at']);
    }

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/templates/{filename}', function ($filename) {
    $cleanFilename = basename($filename);
    $path = storage_path('app/templates/' . $cleanFilename);
    if (!file_exists($path)) {
        $path = public_path('templates/' . $cleanFilename);
    }
    if (!file_exists($path)) {
        $path = storage_path('app/public/shared_documents/' . $cleanFilename);
    }
    if (!file_exists($path)) {
        abort(404, 'Template not found');
    }
    return response()->download($path, $cleanFilename);
})->where('filename', '[A-Za-z0-9\-_.]+');
