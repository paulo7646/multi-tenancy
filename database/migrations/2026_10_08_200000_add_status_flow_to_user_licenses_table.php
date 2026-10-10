<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        DB::connection('central')->statement('ALTER TABLE user_licenses DROP CONSTRAINT IF EXISTS user_licenses_status_check');
        DB::connection('central')->statement("ALTER TABLE user_licenses ADD CONSTRAINT user_licenses_status_check CHECK (status IN ('requested', 'in_review', 'active', 'inactive'))");
        DB::connection('central')->statement("ALTER TABLE user_licenses ALTER COLUMN status SET DEFAULT 'requested'");
    }

    public function down(): void
    {
        DB::connection('central')->table('user_licenses')
            ->whereIn('status', ['requested', 'in_review'])
            ->update(['status' => 'inactive']);

        DB::connection('central')->statement('ALTER TABLE user_licenses DROP CONSTRAINT IF EXISTS user_licenses_status_check');
        DB::connection('central')->statement("ALTER TABLE user_licenses ADD CONSTRAINT user_licenses_status_check CHECK (status IN ('active', 'inactive'))");
        DB::connection('central')->statement("ALTER TABLE user_licenses ALTER COLUMN status SET DEFAULT 'active'");
    }
};
