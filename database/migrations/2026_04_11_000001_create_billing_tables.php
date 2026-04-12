<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('stripe_product_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->enum('interval', ['monthly', 'annually'])->default('monthly');
            $table->decimal('amount', 10, 2);
            $table->integer('trial_days')->default(0);
            $table->string('stripe_price_id')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['plan_id', 'interval']);
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('feature_name');
            $table->text('description')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature_key']);
        });

        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_customer_id');
            $table->enum('status', [
                'trialing',
                'active',
                'past_due',
                'canceled',
                'unpaid',
                'incomplete',
                'incomplete_expired'
            ])->default('active');
            $table->enum('interval', ['monthly', 'annually'])->default('monthly');
            $table->decimal('amount', 10, 2);
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('stripe_customer_id');
        });

        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained('customer_subscriptions')->nullableOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullableOnDelete();
            $table->string('event_type');
            $table->text('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('event_type');
            $table->text('payload');
            $table->boolean('processed')->default(false);
            $table->text('error')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'processed']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('customer_subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('subscription_plans');
    }
};
