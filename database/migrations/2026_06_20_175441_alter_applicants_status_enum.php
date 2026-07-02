<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Replaces 'admitted' with 'approved' and adds 'under_review'
        DB::statement("ALTER TABLE applicants MODIFY COLUMN status ENUM('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE applicants MODIFY COLUMN status ENUM('pending','admitted','rejected') NOT NULL DEFAULT 'pending'");
    }
};
