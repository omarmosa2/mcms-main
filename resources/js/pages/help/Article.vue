<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Clock, HelpCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

type Props = {
    slug: string;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Help Center', href: '/help' },
            { title: 'Article', href: '/help' },
        ],
    },
});

const articles: Record<string, { title: string; content: string; category: string; readTime: string }> = {
    'getting-started': {
        title: 'Getting Started with MCMS',
        category: 'Getting Started',
        readTime: '5 min read',
        content: `
## Welcome to MCMS

MCMS (Medical Clinic Management System) is a comprehensive platform designed to streamline your clinic operations.

### Dashboard Overview

The dashboard provides a bird's-eye view of your clinic's daily operations:

- **Patient Statistics**: Total patients, new registrations, and active visits
- **Queue Status**: Current queue length, waiting patients, and in-service count
- **Appointments**: Today's appointments, upcoming, and completed
- **Financial Summary**: Revenue, pending invoices, and payments

### Navigation

Use the sidebar to navigate between modules:

1. **Dashboard** - Overview and analytics
2. **Patients** - Patient registry and records
3. **Queue** - Live patient flow management
4. **Appointments** - Scheduling and calendar
5. **Billing** - Invoices and payments
6. **Reports** - Analytics and exports
7. **Settings** - System configuration

### Quick Actions

- Click the **+** button on any page to create new records
- Use the **search bar** to quickly find patients or records
- Access **notifications** from the bell icon in the header
        `,
    },
    'creating-patients': {
        title: 'Creating Your First Patient Record',
        category: 'Patient Management',
        readTime: '4 min read',
        content: `
## Patient Records

Patient records are the foundation of your clinic management system.

### Creating a New Patient

1. Navigate to **Patients** from the sidebar
2. Fill in the required fields:
   - **First Name** and **Last Name** (required)
   - **Date of Birth** (recommended)
   - **Gender** (recommended)
   - **Phone** (recommended for notifications)
3. Optional fields:
   - **Email** - For email notifications
   - **National ID** - Encrypted for security
   - **Emergency Contact** - For urgent situations
   - **Notes** - Any additional information

### Chronic Conditions & Allergies

Add medical history using the expandable sections:

- Click **Add** to add new entries
- Each entry is saved separately
- Remove entries using the **Remove** button

### File Number

The file number is auto-generated if left empty. You can also provide a custom MRN (Medical Record Number).
        `,
    },
    'managing-queue': {
        title: 'Managing the Patient Queue',
        category: 'Appointments & Queue',
        readTime: '6 min read',
        content: `
## Queue Management

The queue module manages real-time patient flow through your clinic.

### Enqueuing Patients

1. Select a patient from the dropdown
2. Optionally link an appointment
3. Assign a doctor (optional)
4. Set priority (0-9, higher = more urgent)
5. Add notes if needed
6. Click **Add to queue**

### Calling Next Patient

Click the **Call next** button to automatically call the highest priority waiting patient.

### Status Management

Each queue entry has a status:

- **Waiting** - Patient is in queue
- **Called** - Patient has been called
- **In Service** - Patient is being served
- **Completed** - Service is complete
- **Skipped** - Patient was skipped
- **Canceled** - Entry was canceled

### Live Updates

The queue page updates automatically every 5 seconds. The **Live** indicator shows real-time status.
        `,
    },
    'generating-invoices': {
        title: 'Generating Invoices',
        category: 'Billing & Invoices',
        readTime: '5 min read',
        content: `
## Billing & Invoices

Manage your clinic's financial operations through the billing module.

### Creating an Invoice

1. Navigate to **Billing** > **Invoices**
2. Click **Create Invoice**
3. Select the patient
4. Add line items for services rendered
5. Review the total amount
6. Save and optionally send to patient

### Payment Processing

- Record payments against invoices
- Support for partial payments
- Multiple payment methods (cash, card, insurance)
        `,
    },
    'notification-settings': {
        title: 'Configuring Notifications',
        category: 'Settings & Configuration',
        readTime: '3 min read',
        content: `
## Notification Preferences

Control how and when you receive notifications.

### Available Notification Types

- **Appointment Reminders** - Before scheduled appointments
- **Invoice Issued** - When new invoices are created
- **Prescription Ready** - When prescriptions are ready for pickup

### Channels

Each notification type can be sent via:

- **Email** - Sent to your registered email address
- **SMS** - Sent to your registered phone number (if configured)

### Managing Preferences

1. Go to **Settings** > **Notifications**
2. Toggle email and SMS for each notification type
3. Click **Save Preferences** to apply changes
        `,
    },
    'import-export-data': {
        title: 'Importing and Exporting Data',
        category: 'Patient Management',
        readTime: '4 min read',
        content: `
## Data Import & Export

Bulk operations for patient data management.

### Exporting Patients

1. Go to **Patients** > **Import/Export**
2. Click **Export** tab
3. Click **Download Excel**
4. The file will download with all patient data

### Importing Patients

1. Prepare your Excel or CSV file with required columns:
   - **first_name** (required)
   - **last_name** (required)
2. Optional columns: file_number, date_of_birth, gender, phone, email, national_id, etc.
3. Go to **Patients** > **Import**
4. Drag & drop your file or click to browse
5. Click **Start Import**
6. Monitor progress in the import status section

### Duplicate Detection

The system automatically detects duplicates by:
- File number
- National ID

Duplicate entries will be skipped and reported in the error log.
        `,
    },
    'user-roles': {
        title: 'Understanding User Roles',
        category: 'Settings & Configuration',
        readTime: '5 min read',
        content: `
## User Roles & Permissions

MCMS uses role-based access control (RBAC) to manage user permissions.

### Available Roles

- **Super Admin** - Full access to all features
- **Admin** - Administrative access
- **Clinic Admin** - Clinic-level administration
- **Doctor** - Clinical access with patient data
- **Nurse** - Limited clinical access
- **Receptionist** - Front desk operations
- **Pharmacist** - Pharmacy module access
- **Lab Technician** - Lab module access

### Permission Structure

Permissions follow a hierarchical pattern:
- \`patient.view\` - View patient records
- \`patient.create\` - Create new patients
- \`patient.update\` - Update patient data
- \`patient.delete\` - Delete patient records
        `,
    },
    'contact-support': {
        title: 'Contacting Support',
        category: 'Support',
        readTime: '2 min read',
        content: `
## Getting Help

When you need assistance, we're here to help.

### Support Channels

- **Email**: support@mcms.example.com
- **Phone**: +1-800-MCMS-HELP
- **Live Chat**: Available during business hours (9 AM - 5 PM)

### Before Contacting Support

Please gather the following information:
1. Your user account email
2. Description of the issue
3. Steps to reproduce (if applicable)
4. Screenshots (if helpful)

### Response Times

- **Email**: Within 24 hours
- **Live Chat**: Immediate during business hours
- **Phone**: Immediate during business hours
        `,
    },
};

const article = articles[props.slug] ?? {
    title: 'Article Not Found',
    category: 'Unknown',
    readTime: '1 min read',
    content: 'The requested article could not be found. Please browse our help center for available articles.',
};
</script>

<template>
    <Head :title="article.title" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <div class="flex items-center gap-2">
            <Button variant="neumorphic" size="sm" @click="$inertia.get('/help')">
                <ArrowLeft class="me-2 size-4" />
                Back to Help Center
            </Button>
        </div>

        <article class="glass-panel-soft p-6">
            <div class="mb-6 flex items-center gap-3 border-b border-border/60 pb-4">
                <BookOpen class="size-5 text-muted-foreground" />
                <div>
                    <h1 class="text-xl font-bold">{{ article.title }}</h1>
                    <div class="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                        <span class="rounded-full border border-border/60 bg-background/80 px-2.5 py-0.5">
                            {{ article.category }}
                        </span>
                        <span class="flex items-center gap-1">
                            <Clock class="size-3" />
                            {{ article.readTime }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="prose prose-sm max-w-none dark:prose-invert">
                <div class="whitespace-pre-line text-sm leading-7 text-foreground/85">
                    {{ article.content }}
                </div>
            </div>
        </article>

        <section class="glass-panel-soft p-5">
            <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                Related Articles
            </h3>

            <div class="grid gap-3 sm:grid-cols-2">
                <Link
                    v-for="[slug, related] in Object.entries(articles).filter(([s]) => s !== props.slug).slice(0, 4)"
                    :key="slug"
                    :href="`/help/${slug}`"
                    class="flex items-start gap-3 rounded-xl border border-border/60 bg-background/40 p-4 transition-colors hover:bg-background/60"
                >
                    <HelpCircle class="mt-0.5 size-4 text-muted-foreground" />
                    <div>
                        <h4 class="text-sm font-semibold">{{ related.title }}</h4>
                        <p class="mt-1 text-xs text-muted-foreground">{{ related.category }} · {{ related.readTime }}</p>
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
