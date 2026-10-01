<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Training Counsellor Report - {{ $tc->name }}</title>
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
        
        .badge-capacity {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-leave {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-away {
            background-color: #e0e7ff;
            color: #3730a3;
        }
        
        .badge-inactive {
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
        
        .tag-green {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .tag-warning {
            background-color: #fef3c7;
            color: #92400e;
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
        
        .document-list {
            margin-top: 10px;
        }
        
        .document-item {
            padding: 8px;
            margin-bottom: 5px;
            background-color: #f9fafb;
            border-left: 3px solid #6f1d56;
        }
        
        .document-name {
            font-weight: bold;
            color: #333;
        }
        
        .document-date {
            font-size: 9px;
            color: #6b7280;
        }
        
        .client-list {
            margin-top: 10px;
        }
        
        .client-item {
            padding: 10px;
            margin-bottom: 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        
        .client-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        
        .client-details {
            font-size: 9px;
            color: #6b7280;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th {
            background-color: #f3f4f6;
            padding: 8px;
            text-align: left;
            font-size: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
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
                    <p style="margin: 0; font-size: 14px; font-weight: bold;">Counsellor Report</p>
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
                <div class="info-value">{{ $tc->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">TC ID:</div>
                <div class="info-value">{{ $tc->tc_id }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $tc->email ?? 'Not provided' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value">{{ $tc->phone ?? 'Not provided' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Counsellor Type:</div>
                <div class="info-value">{{ $tc->counsellor_type ?? 'Trainee' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    @php
                        $statusClass = 'badge-active';
                        if ($tc->status === 'At Capacity') $statusClass = 'badge-capacity';
                        elseif ($tc->status === 'On Leave') $statusClass = 'badge-leave';
                        elseif ($tc->status === 'Away') $statusClass = 'badge-away';
                        elseif ($tc->status === 'Inactive') $statusClass = 'badge-inactive';
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $tc->status ?? 'Active' }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Modality:</div>
                <div class="info-value">{{ $tc->modality ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Joined Date:</div>
                <div class="info-value">{{ $tc->joined_date ? \Carbon\Carbon::parse($tc->joined_date)->format('d/m/Y') : 'Not available' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Last Activity:</div>
                <div class="info-value">{{ $tc->last_activity ? \Carbon\Carbon::parse($tc->last_activity)->format('d/m/Y H:i') : 'Not available' }}</div>
            </div>
        </div>
    </div>

    <!-- Training Information -->
    @if($tc->institution || $tc->course || $tc->training_org_name)
    <div class="section">
        <div class="section-title">Training Information</div>
        <div class="info-grid">
            @if($tc->institution || $tc->training_org_name)
            <div class="info-row">
                <div class="info-label">Training Organisation:</div>
                <div class="info-value">{{ $tc->training_org_name ?? $tc->institution }}</div>
            </div>
            @endif
            @if($tc->training_org_address)
            <div class="info-row">
                <div class="info-label">Organisation Address:</div>
                <div class="info-value">{{ $tc->training_org_address }}</div>
            </div>
            @endif
            @if($tc->course || $tc->course_title)
            <div class="info-row">
                <div class="info-label">Course:</div>
                <div class="info-value">{{ $tc->course_title ?? $tc->course }}</div>
            </div>
            @endif
            @if($tc->tutor_name)
            <div class="info-row">
                <div class="info-label">Tutor Name:</div>
                <div class="info-value">{{ $tc->tutor_name }}</div>
            </div>
            @endif
            @if($tc->tutor_email)
            <div class="info-row">
                <div class="info-label">Tutor Email:</div>
                <div class="info-value">{{ $tc->tutor_email }}</div>
            </div>
            @endif
            @if($tc->placement_lead_name)
            <div class="info-row">
                <div class="info-label">Placement Lead:</div>
                <div class="info-value">{{ $tc->placement_lead_name }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Performance Statistics -->
    <div class="section">
        <div class="section-title">Performance Statistics</div>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">{{ $tc->current_clients ?? 0 }}</div>
                <div class="stat-label">Current Clients</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $totalSessions ?? 0 }}</div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $completedSessions ?? 0 }}</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $documents->count() }}</div>
                <div class="stat-label">Documents</div>
            </div>
        </div>
    </div>

    <!-- Clinical Expertise -->
    @if($tc->topics_with_experience || $tc->topics_not_ready_for)
    <div class="section">
        <div class="section-title">Clinical Expertise</div>
        
        @if($tc->topics_with_experience && count($tc->topics_with_experience) > 0)
        <div style="margin-bottom: 15px;">
            <strong style="color: #166534;">✅ Topics with Experience:</strong><br>
            <div style="margin-top: 5px;">
                @foreach($tc->topics_with_experience as $topic)
                    <span class="tag tag-green">{{ $topic }}</span>
                @endforeach
            </div>
        </div>
        @endif
        
        @if($tc->topics_not_ready_for && count($tc->topics_not_ready_for) > 0)
        <div>
            <strong style="color: #92400e;">⚠️ Topics NOT Ready For:</strong><br>
            <div style="margin-top: 5px;">
                @foreach($tc->topics_not_ready_for as $topic)
                    <span class="tag tag-warning">{{ $topic }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- Availability -->
    @if($tc->availability && count($tc->availability) > 0)
    <div class="section">
        <div class="section-title">Availability</div>
        <div style="margin-top: 10px;">
            @foreach($tc->availability as $day => $times)
                <div style="margin-bottom: 5px;">
                    <strong>{{ ucfirst($day) }}:</strong> 
                    @if(is_array($times) && count($times) > 0)
                        {{ implode(', ', $times) }}
                    @else
                        Not available
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Current Clients -->
    @if($clients && $clients->count() > 0)
    <div class="section">
        <div class="section-title">Current Clients ({{ $clients->count() }})</div>
        <div class="client-list">
            @foreach($clients as $client)
            <div class="client-item">
                <div class="client-name">{{ $client->name }}</div>
                <div class="client-details">
                    Client ID: {{ $client->client_id ?? $client->id }} | 
                    @if($client->age) Age: {{ $client->age }} | @endif
                    @if($client->created_at) Joined: {{ $client->created_at->format('d/m/Y') }} @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Documents & Verification -->
    @if($documents && $documents->count() > 0)
    <div class="section">
        <div class="section-title">Documents & Verification</div>
        <div class="document-list">
            @foreach($documents as $doc)
            <div class="document-item">
                <div class="document-name">{{ $doc['name'] }}</div>
                <div class="document-date">
                    @if(isset($doc['uploadDate']))
                        Uploaded: {{ \Carbon\Carbon::parse($doc['uploadDate'])->format('d/m/Y') }}
                    @endif
                    @if(isset($doc['status']))
                        | Status: {{ $doc['status'] }}
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>{{ $companySettings['pdf_footer_text'] ?? 'Confidential — For internal use only' }}</p>
        <p>Report ID: {{ $tc->uuid }} | Generated by {{ $companySettings['company_name'] ?? 'Vanquish' }} Management System</p>
    </div>
</body>
</html>
