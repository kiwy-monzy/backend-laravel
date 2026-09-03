<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Leads — including everything the website contact form collects.
 *
 * **A website enquiry *is* a lead.** It was living in a `messages` table under
 * the Website module, which meant the person who follows up sales had to work
 * two inboxes and neither could tell them whether a customer already existed.
 * Filing it here puts the enquiry next to the pipeline it belongs to, and the
 * `source` column keeps the distinction that actually matters — where it came
 * from — rather than which module happened to receive it.
 *
 * Existing messages are copied across rather than moved, so the website's
 * message list keeps working until its page is retired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            // Which site or campaign produced it; null for one typed in by hand.
            $table->string('website_id')->nullable()->index();
            $table->string('customer_id')->nullable()->index();

            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();

            // website_form | phone | email | walk_in | referral | campaign
            $table->string('source')->default('website_form');
            // new | contacted | qualified | proposal | won | lost
            $table->string('status')->default('new');
            $table->string('owner_id')->nullable()->index();
            $table->decimal('value', 16, 2)->default(0);
            $table->date('follow_up_on')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // Bring the website enquiries over.
        if (! Schema::hasTable('messages')) {
            return;
        }

        $orgByWebsite = DB::table('websites')->pluck('organization_id', 'id');

        foreach (DB::table('messages')->get() as $message) {
            DB::table('crm_leads')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $orgByWebsite[$message->website_id] ?? null,
                'website_id' => $message->website_id,
                'name' => $message->name,
                'email' => $message->email,
                'phone' => $message->phone,
                'subject' => $message->subject,
                'message' => $message->message,
                'source' => 'website_form',
                // A read message has been dealt with; an unread one has not.
                'status' => $message->is_read ? 'contacted' : 'new',
                'created_at' => $message->created_at,
                'updated_at' => $message->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
