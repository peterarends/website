<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemovePriceInfoFromActivities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn([
                'statement',
                'price_member',
                'price_guest',
                'payment_type'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('statement', 16)->after('description');

            $table->unsignedSmallInteger('price_member')->nullable()->default(null)->after('is_public');
            $table->unsignedSmallInteger('price_guest')->nullable()->default(null)->after('price_member');

            $table->string('payment_type', 15)->nullable()->default(null)->after('enrollment_end');
        });
    }
}
