<?php

use App\Models\CustomerSubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->string('current_subscription_key')->nullable()->after('plan_id');
        });

        $rows = DB::table('customer_subscriptions')
            ->orderBy('user_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $occupiedUsers = [];

        foreach ($rows as $row) {
            $shouldOccupy = in_array($row->status, CustomerSubscription::CURRENT_SLOT_STATUSES, true)
                && $row->ended_at === null
                && ! isset($occupiedUsers[$row->user_id]);

            DB::table('customer_subscriptions')
                ->where('id', $row->id)
                ->update([
                    'current_subscription_key' => $shouldOccupy ? "user:{$row->user_id}" : null,
                ]);

            if ($shouldOccupy) {
                $occupiedUsers[$row->user_id] = true;
            }
        }

        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->unique('current_subscription_key');
        });
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['current_subscription_key']);
            $table->dropColumn('current_subscription_key');
        });
    }
};
