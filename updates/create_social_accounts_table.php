<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

class CreateCriSocialloginAccountsTable extends Migration
{
    public function up(): void
    {
        Schema::create('badcookies_sociallogin_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('provider', 32)->index();       // google | facebook
            $table->string('provider_user_id')->index();   // id zwrócone przez OAuth
            $table->text('token')->nullable();             // access token (opcjonalnie)
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badcookies_sociallogin_accounts');
    }
}
