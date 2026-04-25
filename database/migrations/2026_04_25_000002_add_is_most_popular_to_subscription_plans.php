<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('is_most_popular')->default(false)->after('is_active');
        });

        $planId = DB::table('subscription_plans')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->skip(1)
            ->value('id');

        if ($planId === null) {
            $planId = DB::table('subscription_plans')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');
        }

        if ($planId !== null) {
            DB::table('subscription_plans')
                ->where('id', $planId)
                ->update(['is_most_popular' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('is_most_popular');
        });
    }
};
