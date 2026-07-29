// سه خط کنسول برای تست
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StewardKnowledgeFile;
use Illuminate\Support\Facades\Storage;

echo "Testing StewardKnowledgeFile creation:\n";
echo "======================================\n\n";

try {
    // ایجاد یک فایل تستی
    $file = StewardKnowledgeFile::create([
        'title' => 'فایل تستی',
        'original_filename' => 'test.txt',
        'file_path' => 'steward/knowledge/test.txt',
        'file_type' => 'txt',
        'file_size' => 1024,
        'extracted_content' => 'این محتوای تستی است',
        'search_priority' => 5,
        'uploaded_by' => 1,
        'is_active' => true,
    ]);
    
    echo "✅ File created successfully!\n";
    echo "ID: " . $file->id . "\n";
    echo "Title: " . $file->title . "\n\n";
    
    // بررسی اینکه آیا می‌تونیم دوباره query کنیم
    $count = StewardKnowledgeFile::count();
    echo "📊 Total files in database: {$count}\n";
    
} catch (\Exception $e) {
    echo "❌ Error creating file:\n";
    echo $e->getMessage() . "\n";
    echo "\nTrace:\n";
    echo $e->getTraceAsString() . "\n";
}
