<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('cta_type')->default('subscribe')->after('is_most_popular');
            $table->string('contact_url')->nullable()->after('cta_type');
            $table->string('contact_button_text')->nullable()->after('contact_url');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'cta_type',
                'contact_url',
                'contact_button_text',
            ]);
        });
    }
};
