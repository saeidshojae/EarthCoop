import re
from pathlib import Path

# دیکشنری کامل جایگزینی (با پشتیبانی از نسخه‌های مختلف)
repls = {
    # Font Awesome (همه نسخه‌ها)
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css': 
        '{{ asset("vendor/fontawesome/css/all.min.css") }}',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css': 
        '{{ asset("vendor/fontawesome/css/all.min.css") }}',
    # اگر نسخه‌های دیگه‌ای هم هست، اینجا اضافه کن
    
    # jQuery (همه نسخه‌ها)
    'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js': 
        '{{ asset("vendor/jquery/jquery.min.js") }}',
    
    # Persian Date (unpkg)
    'https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js': 
        '{{ asset("vendor/persian-date/persian-date.min.js") }}',
    
    # Three.js
    'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js': 
        '{{ asset("vendor/three.js/three.min.js") }}',
    
    # CropperJS (جدید)
    'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css': 
        '{{ asset("vendor/cropperjs/cropper.min.css") }}',
    'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js': 
        '{{ asset("vendor/cropperjs/cropper.min.js") }}',
    
    # Select2 (اگر هنوز هست)
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css': 
        '{{ asset("vendor/select2/css/select2.min.css") }}',
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js': 
        '{{ asset("vendor/select2/js/select2.min.js") }}',
    
    # CKEditor (اگر هنوز هست)
    'https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js': 
        '{{ asset("vendor/ckeditor/ckeditor.js") }}',
}

# پوشه‌هایی که باید اسکن بشن (کل پروژه به جز موارد استثنا)
scan_root = Path(".")
exclude_patterns = [
    "storage/backups",
    "storage/framework",
    "vendor",
    "node_modules",
    ".git",
]

def should_exclude(path):
    path_str = str(path).replace("\\", "/")
    for pattern in exclude_patterns:
        if pattern in path_str:
            return True
    return False

updated_files = []
total_scanned = 0

for file in scan_root.rglob("*"):
    if not file.is_file():
        continue
    if should_exclude(file):
        continue
    if not (file.suffix in ['.blade.php', '.php', '.html', '.htm']):
        continue
    
    total_scanned += 1
    try:
        content = file.read_text(encoding="utf-8")
    except:
        continue
    
    original = content
    changed = False
    
    for old, new in repls.items():
        if old in content:
            content = content.replace(old, new)
            changed = True
            print(f"   🔄 {old} → {new}")
    
    if changed:
        file.write_text(content, encoding="utf-8")
        updated_files.append(str(file))
        print(f"✅ اصلاح شد: {file}")

print(f"\n📊 تعداد کل فایل‌های اسکن شده: {total_scanned}")
print(f"✅ تعداد فایل‌های اصلاح شده: {len(updated_files)}")
if updated_files:
    print("\n📁 لیست فایل‌ها:")
    for f in updated_files:
        print(f"  - {f}")
else:
    print("⚠️ هیچ فایلی اصلاح نشد.")