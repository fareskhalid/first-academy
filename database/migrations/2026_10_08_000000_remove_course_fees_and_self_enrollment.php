<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $offeringColumns = array_values(array_filter(
            ['fee_minor', 'currency', 'self_enrollment'],
            fn (string $column) => Schema::hasColumn('course_offerings', $column),
        ));
        if ($offeringColumns !== []) {
            Schema::table('course_offerings', fn (Blueprint $table) => $table->dropColumn($offeringColumns));
        }

        $enrollmentColumns = array_values(array_filter(
            ['agreed_fee_minor', 'currency'],
            fn (string $column) => Schema::hasColumn('enrollments', $column),
        ));
        if ($enrollmentColumns !== []) {
            Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn($enrollmentColumns));
        }
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->boolean('self_enrollment')->default(false);
        });
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('agreed_fee_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
        });
    }
};
