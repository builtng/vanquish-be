<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\Client;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ConsultationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get clients
        $emma = Client::where('client_id', 'CL001')->first();
        $john = Client::where('client_id', 'CL002')->first();
        $michael = Client::where('client_id', 'CL011')->first();
        $daniel = Client::where('client_id', 'CL021')->first();

        $consultations = [
            // Completed consultations
            [
                'consultation_id' => 'CON001',
                'client_id' => $emma->id ?? null,
                'tc_id' => null,
                'scheduled_at' => Carbon::parse('2025-02-20 14:00'),
                'completed_at' => Carbon::parse('2025-02-20 15:00'),
                'status' => 'completed',
                'duration_minutes' => 60,
                'notes' => 'Client presented with work-related anxiety. Discussed coping strategies. Recommended for Low Cost Counselling. Client suitable for CBT approach.',
                'outcome' => 'approved',
                'recommended_service' => 'Low Cost Counselling',
                'recommended_modality' => 'CBT',
                'risk_notes' => 'No immediate risk concerns.',
                'next_steps' => 'Move to Pending Match stage.',
                'send_confirmation' => true,
                'payment_status' => 'paid',
                'payment_amount' => 13.00,
                'paid_at' => Carbon::parse('2025-02-18'),
            ],
            [
                'consultation_id' => 'CON002',
                'client_id' => $john->id ?? null,
                'tc_id' => null,
                'scheduled_at' => Carbon::parse('2025-01-25 10:00'),
                'completed_at' => Carbon::parse('2025-01-25 11:00'),
                'status' => 'completed',
                'duration_minutes' => 60,
                'notes' => 'Client dealing with relationship breakdown and depression. Recommended for Low Cost Counselling. Person-Centred approach suitable.',
                'outcome' => 'approved',
                'recommended_service' => 'Low Cost Counselling',
                'recommended_modality' => 'Person-Centred',
                'risk_notes' => 'No immediate risk concerns.',
                'next_steps' => 'Move to Pending Match stage.',
                'send_confirmation' => true,
                'payment_status' => 'paid',
                'payment_amount' => 13.00,
                'paid_at' => Carbon::parse('2025-01-23'),
            ],
            [
                'consultation_id' => 'CON003',
                'client_id' => $michael->id ?? null,
                'tc_id' => null,
                'scheduled_at' => Carbon::parse('2025-02-27 15:00'),
                'completed_at' => Carbon::parse('2025-02-27 16:00'),
                'status' => 'completed',
                'duration_minutes' => 60,
                'notes' => 'Client experiencing work stress and communication difficulties. Recommended for Low Cost Counselling. Integrative approach suitable.',
                'outcome' => 'approved',
                'recommended_service' => 'Low Cost Counselling',
                'recommended_modality' => 'Integrative',
                'risk_notes' => 'No immediate risk concerns.',
                'next_steps' => 'Move to Pending Match stage.',
                'send_confirmation' => true,
                'payment_status' => 'paid',
                'payment_amount' => 13.00,
                'paid_at' => Carbon::parse('2025-02-25'),
            ],
            [
                'consultation_id' => 'CON004',
                'client_id' => $daniel->id ?? null,
                'tc_id' => null,
                'scheduled_at' => Carbon::parse('2025-03-20 11:00'),
                'completed_at' => Carbon::parse('2025-03-20 12:00'),
                'status' => 'completed',
                'duration_minutes' => 60,
                'notes' => 'Client struggling with depression and low self-esteem. Recommended for Low Cost Counselling.',
                'outcome' => 'approved',
                'recommended_service' => 'Low Cost Counselling',
                'recommended_modality' => 'Integrative',
                'risk_notes' => 'No immediate risk concerns.',
                'next_steps' => 'Move to Pending Match stage.',
                'send_confirmation' => true,
                'payment_status' => 'paid',
                'payment_amount' => 13.00,
                'paid_at' => Carbon::parse('2025-03-18'),
            ],
            // Upcoming consultations
            [
                'consultation_id' => 'CON005',
                'client_id' => $emma->id ?? null,
                'tc_id' => 1, // Sarah Johnson
                'scheduled_at' => Carbon::parse('2025-03-28 14:00'),
                'status' => 'scheduled',
                'duration_minutes' => 60,
                'send_confirmation' => true,
                'payment_status' => 'pending',
            ],
            [
                'consultation_id' => 'CON006',
                'client_id' => $john->id ?? null,
                'tc_id' => 2, // David Chen
                'scheduled_at' => Carbon::parse('2025-03-27 10:00'),
                'status' => 'scheduled',
                'duration_minutes' => 60,
                'send_confirmation' => true,
                'payment_status' => 'pending',
            ],
        ];

        foreach ($consultations as $consultation) {
            if ($consultation['client_id']) {
                Consultation::updateOrCreate(
                    ['consultation_id' => $consultation['consultation_id']],
                    $consultation
                );
            }
        }

        $this->command->info('Consultations seeded successfully!');
    }
}

