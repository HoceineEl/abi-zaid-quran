<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'follower', 'teacher'])->default('follower')->change();
            $table->string('phone')->nullable()->change();
            $table->string('sex')->default('male');
        });

        // Transfer teachers to users
        $teachers = DB::table('teachers')->get();

        // Drop existing foreign key first
        Schema::table('memorizers', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn('teacher_id');
        });

        Schema::table('memorizers', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach ($teachers as $teacher) {
            DB::table('users')->insert([
                'name' => $teacher->name,
                'email' => Str::slug($teacher->name).rand(100, 999).'@association.com',
                'phone' => $teacher->phone,
                'password' => bcrypt(Str::random(10)),
                'role' => 'teacher',
                'sex' => $teacher->sex,
                'created_at' => $teacher->created_at ?? now(),
                'updated_at' => $teacher->updated_at ?? now(),
            ]);

            // Update memorizers to use new user_id
            $userId = DB::getPdo()->lastInsertId();
            DB::table('memorizers')
                ->where('teacher_id', $teacher->id)
                ->update(['teacher_id' => $userId]);
        }

        // Drop teachers table
        Schema::dropIfExists('teachers');
    }

    public function down(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->enum('sex', ['male', 'female']);
            $table->timestamps();
        });
    }
};
