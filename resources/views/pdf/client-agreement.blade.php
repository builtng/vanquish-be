<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Agreement - {{ $client->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .header h1 { font-size: 22px; margin-bottom: 4px; }
        .header p { font-size: 11px; opacity: 0.9; }
        .contract-title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            color: #6f1d56;
            margin: 16px 0;
        }
        p { margin-bottom: 10px; text-align: justify; }
        strong { color: #111; }
        .clause-label { font-weight: bold; color: #111; }
        .info-grid { display: table; width: 100%; margin: 16px 0; border: 1px solid #e2e8f0; border-radius: 6px; }
        .info-row { display: table-row; }
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 6px 10px;
            width: 35%;
            color: #555;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-value {
            display: table-cell;
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #6f1d56;
            margin: 18px 0 8px;
            border-bottom: 2px solid #6f1d56;
            padding-bottom: 4px;
        }
        .signature-block { margin-top: 16px; page-break-inside: avoid; }
        .signature-block img { max-height: 70px; display: block; margin: 6px 0; }
        .signature-line { border-bottom: 1px solid #333; width: 250px; display: inline-block; height: 1px; }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Vanquish Therapies</h1>
        <p>Vanquish Services Limited Trading as Vanquish Therapies</p>
        <p>T: 0800 008 6556 | E: help@vanquishtherapies.co.uk | W: www.vanquishtherapies.co.uk</p>
    </div>

    <div class="contract-title">Client Agreement &mdash; {{ $serviceType }} Service</div>

    <div class="contract-title" style="font-size: 12px;">PLEASE READ THIS CONTRACT CAREFULLY</div>

    <p>
        Agreement between <strong>{{ $client->name }}</strong> (referred to as
        &ldquo;you&rdquo;, &ldquo;your&rdquo; and &ldquo;client/clients&rdquo;) and
        <strong>Vanquish Therapies</strong>. (Your assigned Trainee
        Counsellor/Coach will contract with you verbally).
    </p>

    <p>
        <strong>Vanquish Therapies</strong> will provide you with a confidential, safe space to explore
        personal and relational issues and support you through the process without judgement. During
        your sessions, goals will be agreed, and you (the client) agrees to work towards them. If at any
        time the Trainee Counsellor/Coach or Client feels they can no longer have a therapeutic
        relationship for any reason, <strong>Vanquish Therapies</strong> will, where possible, offer to
        refer the client to an alternative Trainee Counsellor/Coach, depending on availability.
    </p>

    @if($serviceType === 'Low Cost')
        <p><span class="clause-label">Consent:</span> Are you willing to be part of an anonymised written case study?
        (This is a requirement for Trainee Counsellors/Coaches). Please be assured there will be no identifiable
        information in the case study that will identify you. Under no circumstances will your sessions be audio or
        video recorded. &mdash; Client response: <strong>{{ $caseStudyConsent }}</strong></p>

        <p><span class="clause-label">Confidentiality:</span> <strong>Vanquish Therapies</strong> has a legal, professional,
        and ethical obligation to do all we can to protect your confidentiality. We are bound by the ethical standards
        of the National Counselling and Psychotherapy Society (NCPS), The British Association for Counselling &amp;
        Psychotherapy (BACP), and Association For Coaching. This means that information provided by you (the client)
        to our staff members, Counsellors, Trainee Counsellors, Coaches, and to anyone else acting on behalf of
        <strong>Vanquish Therapies</strong>, will not be shared outside of our organisation, unless we are legally or
        professionally required to do so. <strong>Vanquish Therapies</strong> will never share information about our
        client for commercial or personal gain however, on rare occasions, breaking confidentiality might be necessary
        to protect everyone from serious harm or to comply with the law. Please contact us to read our
        Confidentiality Policy.</p>

        <p><span class="clause-label">Sessions:</span> Our Counsellors/Coaches offer you a commitment and reserve your
        weekly session and will not allocate this time to anyone else. For this reason, we ask you to commit to
        attending weekly sessions. We cannot offer sessions fortnightly, monthly or intermittently, and we cannot
        offer extended or repeated breaks during your therapeutic contract.</p>

        <p><span class="clause-label">Counselling:</span> The sessions with your assigned Counsellor will be capped at a
        maximum of <strong>50 Sessions/hours</strong>. This includes all scheduled sessions (whether attended, missed,
        or cancelled).</p>

        <p><span class="clause-label">Coaching:</span> The sessions with your assigned Coach will be capped at a maximum
        of <strong>24 sessions/hours</strong>. This includes all scheduled sessions (whether attended, missed, or
        cancelled).</p>

        <p>Upon completion of the allocated hours, there may be an opportunity to continue with further sessions, this
        may involve working with a different counsellor or coach. Sessions will be conducted online via Zoom. The
        client is required to provide their first name or an agreed-upon alternative name prior (during the
        consultation, email or WhatsApp) when joining the online sessions. If the Client does not adhere to this
        requirement, the session will not proceed.</p>

        <p><span class="clause-label">Bookings &amp; Payments:</span> Sessions must be booked <strong>at least 48
        hours</strong> in advance; after this time, the booking system will automatically close your slot for that
        week. Sessions must be booked in blocks of four. This helps ensure your assigned therapeutic space is secured
        with your assigned counsellor/coach even if you are unable to attend a session.</p>

        <p>If sessions are missed, creating a gap, for example, if you finish one block and you miss a week before
        booking another block, any new payment made will first be applied to cover that gap in order to maintain your
        therapeutic space. Any extended gaps or a lack of continuity without prior communication will result in your
        space being reassigned at the discretion of management.</p>

        <p><span class="clause-label">Rescheduling:</span> Sessions <strong>cannot</strong> be rescheduled within the
        low-cost service. If you are unable to attend a scheduled session, regardless of reason(s), it will be
        cancelled. No refunds or session credits will be provided for missed/cancelled sessions. If due to any reason
        your Counsellor/Coach has to cancel a session, <strong>Vanquish Therapies</strong> will aim to give you
        <strong>48-hours notice</strong> and will reschedule your session free of charge. We strongly encourage you to
        read our Booking, Rescheduling, &amp; Cancellation Policy.</p>

        <p><span class="clause-label">Cancellation of Service:</span> If you do not wish to continue using our services
        and want to cancel any scheduled sessions, you can do so up to <strong>48 hours prior</strong> to the
        scheduled session. The 48-hour notice must be given during our administrative and operational hours,
        Monday&ndash;Friday from 9am&ndash;5pm (UK Time). For sessions scheduled on a Monday or Tuesday, notice must
        be given by 5pm (UK Time) on the preceding Friday. Cancelled Sessions within this 48-hour window will be
        refunded the payment made. Cancellations made after this period will not be refunded.</p>

        <p><span class="clause-label">Technical Issues:</span> Should there be any technical issues on the
        client&rsquo;s end (e.g., poor internet connection) the Counsellor/Coach will wait for you on Zoom for
        <strong>15 minutes</strong>, after which they will leave as there will not be enough time to conduct an
        effective session. The session will still be counted and cannot be rescheduled/refunded or credited. In case
        of any technical issues caused on the Counsellors/Coaches end, <strong>Vanquish Therapies</strong> will
        reschedule your session free of charge. In these situations, you will be contacted by the Support
        Coordinators.</p>

        <p>Please be aware: Sessions will <strong>not</strong> proceed if the cameras are <strong>turned off</strong>.
        If, for any reason, you are unable to use your camera, your counsellor/coach will not move forward with the
        session, and you will not be able to reschedule this session. No refunds or session credits will be
        provided.</p>

        <p><span class="clause-label">Communications:</span> Clients will <strong>not</strong> communicate with their
        assigned Trainee Counsellor/Coach outside of the session. For bookings, rescheduling, cancellations, or
        general enquiries, the client will contact <strong>Vanquish Therapies</strong> via email or WhatsApp. Please
        note &ndash; Vanquish Therapies and online Counselling/Coaching are <strong>not</strong> a crisis or emergency service. If you
        need to speak to someone immediately, please contact your <strong>GP</strong>, <strong>NHS (111)</strong>, or
        the <strong>Samaritans (116 123)</strong>.</p>

        <p><span class="clause-label">Termination of service:</span> We understand that your life circumstances may
        suddenly change. You may at any point desire or be obligated to discontinue therapy/coaching. Whatever the
        reason, we respect your decision and ask that you give your Counsellor/Coach as much notice as possible to
        have a closing session.</p>

        <p><span class="clause-label">Complaints:</span> If for any reason you are unhappy with the service you have
        received or dissatisfied with your assigned Counsellor/Coach, please contact <strong>Vanquish
        Therapies</strong> via email. The Support Coordinators will arrange a discussion between you and one of our
        Managers to address and resolve the issues. Failing this, you will receive guidance on how to proceed with
        the complaint&rsquo;s procedure.</p>
    @else
        {{-- Mid-range clause text is a best-effort draft pending the source
             "VQT Agreement - Mid Range.pdf" document; replace once supplied. --}}
        <p><span class="clause-label">Purpose of Counselling:</span> Counselling sessions are provided to support your
        emotional wellbeing and personal development. If, at any point, your Counsellor believes your needs would be
        better met by another service, they will discuss this with you and, where appropriate, provide a
        referral.</p>

        <p><span class="clause-label">Confidentiality:</span> <strong>Vanquish Therapies</strong> has a legal,
        professional, and ethical obligation to do all we can to protect your confidentiality. We are bound by the
        ethical standards of the National Counselling and Psychotherapy Society (NCPS), The British Association for
        Counselling &amp; Psychotherapy (BACP), and Association For Coaching. Please contact us to read our
        Confidentiality Policy.</p>

        <p><span class="clause-label">Sessions:</span> You are committing to attend sessions on a consistent weekly
        basis. Sessions are delivered online via Zoom. Sessions will <strong>not</strong> proceed if cameras are
        switched off.</p>

        <p><span class="clause-label">Bookings &amp; Payments:</span> Sessions must be booked <strong>at least 48
        hours</strong> in advance, with payment required in advance of each booking.</p>

        <p><span class="clause-label">Rescheduling:</span> Sessions may be rescheduled with at least
        <strong>48 hours&rsquo;</strong> notice. Requests made within 48 hours of the session time may not be
        accommodated.</p>

        <p><span class="clause-label">Cancellation of Service:</span> Cancellations require at least 48 hours&rsquo;
        notice. Cancellations made within the 48-hour window are non-refundable, as the slot has already been
        reserved on your behalf.</p>

        <p><span class="clause-label">Technical Issues:</span> If you experience technical difficulties joining a
        session, your Counsellor will wait up to <strong>15 minutes</strong> before the session is treated as a
        missed session on your part.</p>

        <p><span class="clause-label">Communications:</span> Please do not contact your Counsellor directly outside of
        scheduled sessions. All administrative communication should go through Vanquish Therapies via
        help@vanquishtherapies.co.uk. Please note &ndash; Vanquish Therapies and online counselling are
        <strong>not</strong> a crisis or emergency service. If you need to speak to someone immediately, please
        contact your <strong>GP</strong>, <strong>NHS (111)</strong>, or the <strong>Samaritans (116 123)</strong>.</p>

        <p><span class="clause-label">Termination of therapy:</span> Either you or your Counsellor may end the
        therapeutic relationship at any time. Where possible, we ask that you let us know in advance so a closing
        session can be arranged.</p>

        <p><span class="clause-label">Complaints:</span> If you have a concern or complaint about your sessions,
        please contact Vanquish Therapies via email and it will be handled promptly and confidentially.</p>
    @endif

    <p style="font-style: italic;">
        This agreement shall be construed and governed in all respects in accordance with the laws of England and
        any dispute or differences in relation to this agreement shall be subject to the exclusive jurisdiction of
        the English Courts.
    </p>

    <p><strong>
        This contract is intended to explain the practicalities of the Therapeutic agreement. In signing, you are
        agreeing to the above terms and conditions and the related policies (which include the provision for breach
        of confidentiality in those rare circumstances described above).
    </strong></p>

    <p><strong>
        I agree to adhere to all the policies and procedures set forth by Vanquish Therapies, including the
        prohibition against recording any sessions.
    </strong></p>

    <div class="section-title">Client Details</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Full Name</div>
            <div class="info-value">{{ $client->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Email</div>
            <div class="info-value">{{ $client->email }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Current Address</div>
            <div class="info-value">{{ $client->current_address }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Emergency Contact</div>
            <div class="info-value">{{ $client->emergency_contact_name }} ({{ $client->emergency_contact_relationship }}) &mdash; {{ $client->emergency_contact_phone }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">GP</div>
            <div class="info-value">{{ $client->gp_name }}, {{ $client->gp_practice_name }} &mdash; {{ $client->gp_practice_phone }}</div>
        </div>
    </div>

    <div class="signature-block">
        <div class="clause-label">Client Signature:</div>
        @if($signaturePath)
            <img src="{{ $signaturePath }}" alt="Client signature">
        @else
            <div class="signature-line"></div>
        @endif
        <p>Date: {{ $signedDate }}</p>
    </div>

    <div class="signature-block">
        <div class="clause-label">On Behalf of Vanquish Therapies:</div>
        <p style="font-family: 'DejaVu Sans', cursive; font-style: italic; font-size: 14px;">I. Jawando</p>
        <p>Date: As stated above by the client.</p>
    </div>

    <div class="footer">
        This document was generated electronically by Vanquish Therapies on {{ now()->format('d/m/Y H:i') }} UK time
        and reflects the agreement submitted and signed by the client via the Vanquish Therapies client portal.
    </div>
</body>
</html>
