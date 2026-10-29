<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_intents', function (Blueprint $table) {
            $table->string('method', 20)->default('qr')->after('qr_credential_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_intents', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
