# 🧠 Vanquish Backend API

The technical core of the Vanquish Therapy Management System, built with **Laravel 12**.

## 🏗️ System Architecture

The backend operates as a headless API service, handling business logic, data persistence, and external service integrations.

### **Core Stack**
- **Framework**: Laravel 12.x
- **Authentication**: Laravel Sanctum (Token-based)
- **Database**: MySQL (Eloquent ORM)
- **Mailing**: PHP Mailer with Dynamic HTML Templates
- **Queue**: Database Driver for Asynchronous Jobs

---

## 🚀 Recruitment & Onboarding Engine

The system features a complex 4-stage recruitment pipeline managed via `TraineeApplicationController`.

### **Stage 1: Application Ingestion**
- **Trigger**: JotForm Webhook (`/api/webhooks/jotform`).
- **Logic**: Parses PII and document URLs. Avoids duplicates. Triggers acknowledgement email.
- **Reference**: `JotFormWebhookController`.

### **Stage 2: Video Screening**
- **Trigger**: 48h delayed Job (`SendTraineeStageTwoInvite`).
- **Partner**: HireVire.
- **Submission**: `HireVireWebhookController` captures the video response and promotes status.

### **Stage 3: Professional Interview**
- **Trigger**: Admin 'Approve' action.
- **Partner**: Trafft & Zoom.
- **Webhook**: `TrafftWebhookController` handles `appointment_confirmed`, extracting Zoom URLs and scheduling reminders.

### **Stage 4: Compliance & Activation**
- **Logic**: Interactive onboarding checklist.
- **Actions**: Agreement tracking, induction attendance, and SuiteDash portal invitation.
- **Finality**: `finalizePlacement` promotes the application to the `TrainingCounsellor` domain.

---

## 🛠️ Webhook Directory

| Endpoint | Service | Purpose |
| :--- | :--- | :--- |
| `POST /api/webhooks/jotform` | JotForm | New Client/Trainee applications. |
| `POST /api/webhooks/trafft` | Trafft | Booking confirmation & Zoom link storage. |
| `POST /api/webhooks/hirevire` | HireVire | Video interview submission capture. |

---

## 📧 Dynamic Email System

Emails are managed through a single entry point: `App\Mail\DynamicEmail`. 
Templates are defined in `DynamicEmail::getTemplates()` and support nested HTML components.

To add a new template:
1.  Define the key and HTML body in `DynamicEmail.php`.
2.  Add required placeholders to the `placeholders` array.
3.  Trigger via `Mail::to($user)->send(new DynamicEmail('template_key', $data))`.

---

## 🔐 Security & ACL

- **Roles**: `admin` (Full access), `staff` (Coordinator), `counsellor` (Portal access).
- **Rate Limiting**: Configured in `RouteServiceProvider` (Standard: 60/min).
- **CORS**: Managed in `config/cors.php`.

---

## 📦 Maintenance Commands

**Run Queued Jobs**:
```bash
php artisan queue:work --queue=default,emails
```

**Clear Application Cache**:
```bash
php artisan optimize:clear
```

**Run Automated Tests**:
```bash
php artisan test
```

---
*Technical Lead: Antigravity AI*
