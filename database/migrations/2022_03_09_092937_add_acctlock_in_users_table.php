<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAcctlockInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->boolean('acctlock')->default(false)->after('remember_token');
            $table->dateTime('last_signon_at')->nullable()->after('acctlock');
            $table->dateTime('last_sync_at')->nullable()->after('last_signon_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropColumn('acctlock');
            $table->dropColumn('last_signon_at');
            $table->dropColumn('last_sync_at');
            
        });
    }
}
