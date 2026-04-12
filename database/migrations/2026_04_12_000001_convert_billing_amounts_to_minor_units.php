<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql' => $this->convertForMySql(),
            'pgsql' => $this->convertForPostgres(),
            'sqlite' => $this->convertForSqlite(),
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for billing amount conversion."),
        };
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql' => $this->revertForMySql(),
            'pgsql' => $this->revertForPostgres(),
            'sqlite' => $this->revertForSqlite(),
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for billing amount conversion rollback."),
        };
    }

    private function convertForMySql(): void
    {
        DB::transaction(function () {
            DB::statement('UPDATE plan_prices SET amount = ROUND(amount * 100)');
            DB::statement('UPDATE customer_subscriptions SET amount = ROUND(amount * 100)');

            DB::statement('ALTER TABLE plan_prices MODIFY amount BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE customer_subscriptions MODIFY amount BIGINT UNSIGNED NOT NULL');
        });
    }

    private function revertForMySql(): void
    {
        DB::transaction(function () {
            DB::statement('ALTER TABLE plan_prices MODIFY amount DECIMAL(10, 2) NOT NULL');
            DB::statement('ALTER TABLE customer_subscriptions MODIFY amount DECIMAL(10, 2) NOT NULL');

            DB::statement('UPDATE plan_prices SET amount = amount / 100');
            DB::statement('UPDATE customer_subscriptions SET amount = amount / 100');
        });
    }

    private function convertForPostgres(): void
    {
        DB::transaction(function () {
            DB::statement('UPDATE plan_prices SET amount = ROUND(amount * 100)');
            DB::statement('UPDATE customer_subscriptions SET amount = ROUND(amount * 100)');

            DB::statement('ALTER TABLE plan_prices ALTER COLUMN amount TYPE BIGINT USING ROUND(amount)::BIGINT');
            DB::statement('ALTER TABLE customer_subscriptions ALTER COLUMN amount TYPE BIGINT USING ROUND(amount)::BIGINT');
        });
    }

    private function revertForPostgres(): void
    {
        DB::transaction(function () {
            DB::statement('ALTER TABLE plan_prices ALTER COLUMN amount TYPE DECIMAL(10, 2) USING (amount::DECIMAL / 100)');
            DB::statement('ALTER TABLE customer_subscriptions ALTER COLUMN amount TYPE DECIMAL(10, 2) USING (amount::DECIMAL / 100)');
        });
    }

    private function convertForSqlite(): void
    {
        DB::transaction(function () {
            DB::statement('UPDATE plan_prices SET amount = ROUND(amount * 100)');
            DB::statement('UPDATE customer_subscriptions SET amount = ROUND(amount * 100)');

            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::table('plan_prices', function (Blueprint $table) {
                $table->unsignedBigInteger('amount_minor')->default(0);
            });

            DB::statement('UPDATE plan_prices SET amount_minor = CAST(amount AS INTEGER)');

            Schema::table('plan_prices', function (Blueprint $table) {
                $table->dropColumn('amount');
            });

            Schema::table('plan_prices', function (Blueprint $table) {
                $table->renameColumn('amount_minor', 'amount');
            });

            Schema::table('customer_subscriptions', function (Blueprint $table) {
                $table->unsignedBigInteger('amount_minor')->default(0);
            });

            DB::statement('UPDATE customer_subscriptions SET amount_minor = CAST(amount AS INTEGER)');

            Schema::table('customer_subscriptions', function (Blueprint $table) {
                $table->dropColumn('amount');
            });

            Schema::table('customer_subscriptions', function (Blueprint $table) {
                $table->renameColumn('amount_minor', 'amount');
            });

            DB::statement('PRAGMA foreign_keys = ON');
        });
    }

    private function revertForSqlite(): void
    {
        DB::transaction(function () {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::table('plan_prices', function (Blueprint $table) {
                $table->decimal('amount_decimal', 10, 2)->default(0);
            });

            DB::statement('UPDATE plan_prices SET amount_decimal = amount / 100.0');

            Schema::table('plan_prices', function (Blueprint $table) {
                $table->dropColumn('amount');
            });

            Schema::table('plan_prices', function (Blueprint $table) {
                $table->renameColumn('amount_decimal', 'amount');
            });

            Schema::table('customer_subscriptions', function (Blueprint $table) {
                $table->decimal('amount_decimal', 10, 2)->default(0);
            });

            DB::statement('UPDATE customer_subscriptions SET amount_decimal = amount / 100.0');

            Schema::table('customer_subscriptions', function (Blueprint $table) {
                $table->dropColumn('amount');
            });

            Schema::table('customer_subscriptions', function (Blueprint $table) {
                $table->renameColumn('amount_decimal', 'amount');
            });

            DB::statement('PRAGMA foreign_keys = ON');
        });
    }
};
