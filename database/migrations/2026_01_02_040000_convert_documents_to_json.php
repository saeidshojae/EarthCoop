<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // تبدیل داده‌های موجود از comma-separated به JSON
        DB::table('users')->whereNotNull('documents')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $documentsString = $user->documents;
                if ($documentsString && !empty(trim($documentsString))) {
                    $files = explode(',', $documentsString);
                    $documentsArray = [];
                    
                    foreach ($files as $file) {
                        $file = trim($file);
                        if (!empty($file)) {
                            // استخراج extension برای تشخیص نوع فایل
                            $extension = pathinfo($file, PATHINFO_EXTENSION);
                            $documentsArray[] = [
                                'filename' => $file,
                                'name' => 'مدرک', // نام پیش‌فرض
                                'type' => strtolower($extension)
                            ];
                        }
                    }
                    
                    if (!empty($documentsArray)) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['documents' => json_encode($documentsArray, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // تبدیل داده‌ها از JSON به comma-separated
        DB::table('users')->whereNotNull('documents')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $documentsJson = $user->documents;
                if ($documentsJson) {
                    $documentsArray = json_decode($documentsJson, true);
                    if (is_array($documentsArray)) {
                        $files = array_column($documentsArray, 'filename');
                        $documentsString = implode(',', $files);
                        
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['documents' => $documentsString]);
                    }
                }
            }
        });
    }
};
