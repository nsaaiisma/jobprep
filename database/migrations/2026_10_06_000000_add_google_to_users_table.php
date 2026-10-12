<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('google_id')->nullable()->unique()->after('email');
            $t->string('avatar')->nullable()->after('google_id');
            $t->string('password')->nullable()->change(); // akun Google tidak punya password
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['google_id', 'avatar']);
        });
    }
};
