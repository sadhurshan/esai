<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $events = [
            'rfq_created',
            'quote_submitted',
            'po_issued',
            'grn_posted',
            'invoice_created',
            'invoice_status_changed',
            'rfq.clarification.question',
            'rfq.clarification.answer',
            'rfq.clarification.amendment',
            'quote.revision.submitted',
            'quote.withdrawn',
            'rfq_line_awarded',
            'rfq_line_lost',
            'plan_overlimit',
            'certificate_expiry',
            'analytics_query',
            'approvals.pending',
            'rma.raised',
            'rma.reviewed',
            'rma.closed',
            'maintenance_completed',
        ];

        $enumList = "'".implode("','", $events)."'";

        DB::statement("ALTER TABLE notifications MODIFY event_type ENUM($enumList)");
        DB::statement("ALTER TABLE user_notification_prefs MODIFY event_type ENUM($enumList)");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $events = [
            'rfq_created',
            'quote_submitted',
            'po_issued',
            'grn_posted',
            'invoice_created',
            'invoice_status_changed',
            'rfq.clarification.question',
            'rfq.clarification.answer',
            'rfq.clarification.amendment',
            'quote.revision.submitted',
            'quote.withdrawn',
            'plan_overlimit',
            'certificate_expiry',
            'analytics_query',
            'approvals.pending',
            'rma.raised',
            'rma.reviewed',
            'rma.closed',
            'maintenance_completed',
        ];

        $enumList = "'".implode("','", $events)."'";

        DB::statement("ALTER TABLE notifications MODIFY event_type ENUM($enumList)");
        DB::statement("ALTER TABLE user_notification_prefs MODIFY event_type ENUM($enumList)");
    }
};
