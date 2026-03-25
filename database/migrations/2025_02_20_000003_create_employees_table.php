<?php

use App\Enums\EmployeeStatus;
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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('employee_no')->unique()->index();
            $table->enum('status', EmployeeStatus::values())->default(EmployeeStatus::Active->value)->index();
            $table->date('hire_date')->nullable();
            $table->decimal('performance_rating', 5, 2)->nullable();

            // HR Information fields
            $table->string('ssnit_number')->nullable();
            $table->string('ghana_card_id')->nullable();
            $table->string('tin_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable()->default('Ghanaian');

            // Emergency Contact fields
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['employee_id', 'branch_id']);
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_branch');
        Schema::dropIfExists('employees');
    }
};
