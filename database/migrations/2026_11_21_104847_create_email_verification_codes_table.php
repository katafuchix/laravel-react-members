<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            // 会員ごとに1件だけ持つ（再送すると上書きされ、古いコードは無効になる）。
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // コードを発行したメールアドレス。メールを変更したら、古いコードは使えない。
            $table->string('email');
            // 認証コードのハッシュ（平文では保存しない）。
            $table->string('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
