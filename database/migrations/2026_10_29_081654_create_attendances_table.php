<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');                          // 勤務日
            $table->dateTime('clock_in')->nullable();      // 出勤日時
            $table->dateTime('clock_out')->nullable();     // 退勤日時
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            // 同じユーザーが同じ日に重複して出勤データを作らないためのユニーク制約
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};