<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create persons table if not exists
        if (!Schema::hasTable('persons')) {
            Schema::create('persons', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('email')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('type')->default('client'); // client, practitioner, both
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. Add person_id and archived_at to clients
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (!Schema::hasColumn('clients', 'person_id')) {
                    $table->unsignedBigInteger('person_id')->nullable()->after('id');
                    $table->index('person_id');
                }
                if (!Schema::hasColumn('clients', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('updated_at');
                }
            });
        }

        // 3. Add person_id and archived_at to trainee_applications
        if (Schema::hasTable('trainee_applications')) {
            Schema::table('trainee_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('trainee_applications', 'person_id')) {
                    $table->unsignedBigInteger('person_id')->nullable()->after('id');
                    $table->index('person_id');
                }
                if (!Schema::hasColumn('trainee_applications', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('updated_at');
                }
            });
        }

        // 4. Update training_counsellors: drop unique on email if exists, add person_id & archived_at
        if (Schema::hasTable('training_counsellors')) {
            Schema::table('training_counsellors', function (Blueprint $table) {
                try {
                    $table->dropUnique(['email']);
                } catch (\Throwable $e) {
                    try {
                        $table->dropUnique('training_counsellors_email_unique');
                    } catch (\Throwable $e2) {
                        // Ignore
                    }
                }
            });

            Schema::table('training_counsellors', function (Blueprint $table) {
                try {
                    $table->index('email');
                } catch (\Throwable $e) {
                    // Ignore
                }

                if (!Schema::hasColumn('training_counsellors', 'person_id')) {
                    $table->unsignedBigInteger('person_id')->nullable()->after('id');
                    $table->index('person_id');
                }
                if (!Schema::hasColumn('training_counsellors', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('updated_at');
                }
            });
        }

        // 5. Create qc_applications table
        if (!Schema::hasTable('qc_applications')) {
            Schema::create('qc_applications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('person_id')->nullable()->index();
                $table->unsignedBigInteger('training_counsellor_id')->nullable()->index();
                $table->string('legal_first_name');
                $table->string('legal_last_name');
                $table->string('name')->nullable();
                $table->string('email')->index();
                $table->string('phone')->nullable();
                $table->string('status')->default('New Application');
                $table->json('answers')->nullable();
                $table->string('qualification_document')->nullable();
                $table->string('dbs_certificate_qualified')->nullable();
                $table->string('insurance_qualified')->nullable();
                $table->string('self_employment_proof')->nullable();
                $table->string('professional_membership')->nullable();
                $table->string('valid_id_document')->nullable();
                $table->text('signature')->nullable();
                $table->date('signature_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        // 6. Backfill persons from existing records safely
        try {
            $existingEmails = [];

            // From users
            if (Schema::hasTable('users')) {
                $users = DB::table('users')->whereNotNull('email')->get(['id', 'email', 'name']);
                foreach ($users as $u) {
                    $norm = strtolower(trim($u->email));
                    if ($norm && !isset($existingEmails[$norm])) {
                        $existingEmails[$norm] = [
                            'name' => $u->name,
                            'type' => 'practitioner',
                        ];
                    }
                }
            }

            // From training_counsellors
            if (Schema::hasTable('training_counsellors')) {
                $tcs = DB::table('training_counsellors')->whereNotNull('email')->get(['id', 'email', 'name', 'phone']);
                foreach ($tcs as $tc) {
                    $norm = strtolower(trim($tc->email));
                    if ($norm && !isset($existingEmails[$norm])) {
                        $existingEmails[$norm] = [
                            'name' => $tc->name ?: 'Practitioner',
                            'phone' => $tc->phone,
                            'type' => 'practitioner',
                        ];
                    }
                }
            }

            // From clients
            if (Schema::hasTable('clients')) {
                $clients = DB::table('clients')->whereNotNull('email')->get(['id', 'email', 'name', 'first_name', 'last_name', 'phone']);
                foreach ($clients as $c) {
                    $norm = strtolower(trim($c->email));
                    if ($norm && !isset($existingEmails[$norm])) {
                        $existingEmails[$norm] = [
                            'name' => $c->name ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')),
                            'first_name' => $c->first_name,
                            'last_name' => $c->last_name,
                            'phone' => $c->phone,
                            'type' => 'client',
                        ];
                    }
                }
            }

            // From trainee_applications
            if (Schema::hasTable('trainee_applications')) {
                $apps = DB::table('trainee_applications')->whereNotNull('email')->get(['id', 'email', 'first_name', 'last_name', 'phone']);
                foreach ($apps as $a) {
                    $norm = strtolower(trim($a->email));
                    if ($norm && !isset($existingEmails[$norm])) {
                        $existingEmails[$norm] = [
                            'name' => trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')),
                            'first_name' => $a->first_name,
                            'last_name' => $a->last_name,
                            'phone' => $a->phone,
                            'type' => 'practitioner',
                        ];
                    }
                }
            }

            foreach ($existingEmails as $email => $data) {
                $nameParts = explode(' ', trim($data['name'] ?? ''));
                $first = $data['first_name'] ?? ($nameParts[0] ?? '');
                $last = $data['last_name'] ?? (count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '');
                $personId = DB::table('persons')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'email' => $email,
                    'first_name' => $first,
                    'last_name' => $last,
                    'name' => trim($data['name']) ?: $email,
                    'phone' => $data['phone'] ?? null,
                    'type' => $data['type'] ?? 'client',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Link tables
                if (Schema::hasTable('clients')) {
                    DB::table('clients')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->update(['person_id' => $personId]);
                }
                if (Schema::hasTable('training_counsellors')) {
                    DB::table('training_counsellors')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->update(['person_id' => $personId]);
                }
                if (Schema::hasTable('trainee_applications')) {
                    DB::table('trainee_applications')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->update(['person_id' => $personId]);
                }
            }
        } catch (\Throwable $e) {
            // Log but don't fail migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qc_applications');

        if (Schema::hasTable('training_counsellors')) {
            Schema::table('training_counsellors', function (Blueprint $table) {
                if (Schema::hasColumn('training_counsellors', 'person_id')) {
                    $table->dropColumn('person_id');
                }
                if (Schema::hasColumn('training_counsellors', 'archived_at')) {
                    $table->dropColumn('archived_at');
                }
            });
        }

        if (Schema::hasTable('trainee_applications')) {
            Schema::table('trainee_applications', function (Blueprint $table) {
                if (Schema::hasColumn('trainee_applications', 'person_id')) {
                    $table->dropColumn('person_id');
                }
                if (Schema::hasColumn('trainee_applications', 'archived_at')) {
                    $table->dropColumn('archived_at');
                }
            });
        }

        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (Schema::hasColumn('clients', 'person_id')) {
                    $table->dropColumn('person_id');
                }
                if (Schema::hasColumn('clients', 'archived_at')) {
                    $table->dropColumn('archived_at');
                }
            });
        }

        Schema::dropIfExists('persons');
    }
};
