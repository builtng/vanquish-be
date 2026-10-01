<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Report - {{ $client->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        
        .header {
            background-color: #6f1d56;
            color: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #6f1d56;
            margin-bottom: 12px;
            padding-bottom: 5px;
            border-bottom: 2px solid #6f1d56;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 6px 10px 6px 0;
            width: 35%;
            color: #555;
        }
        
        .info-value {
            display: table-cell;
            padding: 6px 0;
            color: #333;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-urgent {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-stuck {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-archived {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        
        .tag {
            display: inline-block;
            padding: 3px 8px;
            margin: 2px;
            background-color: #f3f4f6;
            border-radius: 4px;
            font-size: 9px;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        
        .stat-box {
            display: table-cell;
            text-align: center;
            padding: 15px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            width: 25%;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #6f1d56;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
        }
        
        .consultation-list {
            margin-top: 10px;
        }
        
        .consultation-item {
            padding: 10px;
            margin-bottom: 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div style="display: table; width: 100%;">
            <div style="display: table-row;">
                @if(isset($companySettings['platform_logo_base64']) && $companySettings['platform_logo_base64'])
                <div style="display: table-cell; width: 60px; vertical-align: middle;">
                    <div style="background: white; padding: 5px; border-radius: 4px; width: 50px; height: 50px; text-align: center;">
                        <img src="{{ $companySettings['platform_logo_base64'] }}" style="max-width: 40px; max-height: 40px;">
                    </div>
                </div>
                @elseif(isset($companySettings['platform_logo_url']) && $companySettings['platform_logo_url'])
                <div style="display: table-cell; width: 60px; vertical-align: middle;">
                    <div style="background: white; padding: 5px; border-radius: 4px; width: 50px; height: 50px; text-align: center;">
                        <img src="{{ $companySettings['platform_logo_url'] }}" style="max-width: 40px; max-height: 40px;">
                    </div>
                </div>
                @endif
                <div style="display: table-cell; vertical-align: middle; padding-left: 10px;">
                    <h1 style="margin: 0; font-size: 20px; color: #ffffff;">{{ $companySettings['company_name'] ?? 'Vanquish Training' }}</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 11px; font-weight: bold;">{{ $companySettings['pdf_header_text'] ?? 'Management System Report' }}</p>
                    <div style="font-size: 9px; margin-top: 4px; color: rgba(255,255,255,0.85);">
                        @if(!empty($companySettings['company_address'])) {{ $companySettings['company_address'] }} @endif
                        @if(!empty($companySettings['company_phone'])) | {{ $companySettings['company_phone'] }} @endif
                        @if(!empty($companySettings['company_email'])) | {{ $companySettings['company_email'] }} @endif
                    </div>
                </div>
                <div style="display: table-cell; vertical-align: middle; text-align: right;">
                    <p style="margin: 0; font-size: 14px; font-weight: bold;">Client Report</p>
                    <p style="margin: 0; font-size: 10px; opacity: 0.8;">{{ now()->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Basic Information -->
    <div class="section">
        <div class="section-title">Basic Information</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Full Name:</div>
                <div class="info-value">{{ $client->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Client ID:</div>
                <div class="info-value">{{ $client->client_id }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $client->email ?? 'Not provided' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value">{{ $client->phone ?? 'Not provided' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Age:</div>
                <div class="info-value">{{ $client->age ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Gender:</div>
                <div class="info-value">{{ $client->gender ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Service Type:</div>
                <div class="info-value">{{ $client->service_type ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    @php
                        $statusClass = 'badge-active';
                        if ($client->status === 'urgent') $statusClass = 'badge-urgent';
                        elseif ($client->status === 'stuck') $statusClass = 'badge-stuck';
                        elseif ($client->status === 'archived') $statusClass = 'badge-archived';
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ ucfirst($client->status ?? 'Active') }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Stage:</div>
                <div class="info-value">{{ $client->stage ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Submitted Date:</div>
                <div class="info-value">{{ $client->submitted_date ? \Carbon\Carbon::parse($client->submitted_date)->format('d/m/Y') : 'Not available' }}</div>
            </div>
        </div>
    </div>

    <!-- Clinical Information -->
    <div class="section">
        <div class="section-title">Clinical Information</div>
        <div class="info-grid">
            @if($client->primary_issues && count($client->primary_issues) > 0)
            <div class="info-row">
                <div class="info-label">Primary Issues:</div>
                <div class="info-value">
                    @foreach($client->primary_issues as $issue)
                        <span class="tag">{{ $issue }}</span>
                    @endforeach
                </div>
            </div>
            @endif
            @if($client->medication)
            <div class="info-row">
                <div class="info-label">Medication:</div>
                <div class="info-value">{{ $client->medication }}</div>
            </div>
            @endif
            @if($client->risk_flags)
            <div class="info-row">
                <div class="info-label" style="color: #991b1b;">Risk Flags:</div>
                <div class="info-value" style="color: #991b1b; font-weight: bold;">{{ $client->risk_flags }}</div>
            </div>
            @endif
            @if($gpDetails)
            <div class="info-row">
                <div class="info-label">GP Details:</div>
                <div class="info-value">
                    {{ $gpDetails['name'] ?? '' }}<br>
                    {{ $gpDetails['surgery'] ?? '' }}
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Emergency Contact -->
    @if($emergencyContact)
    <div class="section">
        <div class="section-title">Emergency Contact</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value">{{ $emergencyContact['name'] ?? 'Not provided' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Relationship:</div>
                <div class="info-value">{{ $emergencyContact['relationship'] ?? 'Not provided' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value">{{ $emergencyContact['phone'] ?? 'Not provided' }}</div>
            </div>
        </div>
    </div>
    @endif

    <!-- Matched Counsellor -->
    @if($client->matchedTc)
    <div class="section">
        <div class="section-title">Matched Counsellor</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Counsellor Name:</div>
                <div class="info-value">{{ $client->matchedTc->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">TC ID:</div>
                <div class="info-value">{{ $client->matchedTc->tc_id }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Matched Date:</div>
                <div class="info-value">{{ $client->matched_date ? \Carbon\Carbon::parse($client->matched_date)->format('d/m/Y') : 'Not available' }}</div>
            </div>
        </div>
    </div>
    @endif

    <!-- Consultation History -->
    @if($consultations && $consultations->count() > 0)
    <div class="section">
        <div class="section-title">Consultation History ({{ $consultations->count() }})</div>
        <div class="stats-grid" style="margin-bottom: 15px;">
            <div class="stat-box">
                <div class="stat-value">{{ $consultations->count() }}</div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $consultations->where('status', 'completed')->count() }}</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $consultations->where('status', 'scheduled')->count() }}</div>
                <div class="stat-label">Scheduled</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $consultations->whereIn('status', ['cancelled', 'dna'])->count() }}</div>
                <div class="stat-label">Cancelled/DNA</div>
            </div>
        </div>
        
        <div class="consultation-list">
            @foreach($consultations as $consultation)
            <div class="consultation-item">
                <div style="font-weight: bold;">
                    {{ \Carbon\Carbon::parse($consultation->scheduled_at)->format('d/m/Y h:i A') }}
                    <span style="float: right; font-size: 10px; padding: 2px 6px; background: #eee; border-radius: 4px;">{{ ucfirst($consultation->status) }}</span>
                </div>
                <div style="font-size: 10px; color: #666; margin-top: 3px;">
                    Type: {{ ucfirst($consultation->type) }} | Duration: {{ $consultation->duration }} mins
                </div>
                @if($consultation->notes)
                <div style="margin-top: 5px; font-size: 10px; font-style: italic;">
                    Notes: {{ Str::limit($consultation->notes, 100) }}
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>{{ $companySettings['pdf_footer_text'] ?? 'Confidential — For internal use only' }}</p>
        <p>Report ID: {{ $client->uuid }} | Generated by {{ $companySettings['company_name'] ?? 'Vanquish' }} Management System</p>
    </div>
</body>
</html>
