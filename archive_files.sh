#!/bin/bash
# اسکریپت Bash برای انتقال فایل‌های اضافی به پوشه _archive
# این اسکریپت فایل‌ها را حذف نمی‌کند، فقط به پوشه _archive منتقل می‌کند

# رنگ‌ها برای خروجی
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# ایجاد پوشه _archive
ARCHIVE_PATH="_archive"
mkdir -p "$ARCHIVE_PATH"

# ایجاد زیرپوشه‌ها
subfolders=(
    "backup-old-files"
    "temp-files"
    "test-files"
    "sql-backups"
    "duplicate-files"
    "macosx-folder"
    "new-ui-folder"
    "check-scripts"
    "migration-skip"
)

for folder in "${subfolders[@]}"; do
    mkdir -p "$ARCHIVE_PATH/$folder"
done

echo -e "${GREEN}✅ پوشه‌ها ایجاد شدند${NC}\n"

# تابع برای انتقال فایل
move_to_archive() {
    local source="$1"
    local dest_folder="$2"
    
    if [ -e "$source" ] || [ -f "$source" ]; then
        local filename=$(basename "$source")
        local dest="$ARCHIVE_PATH/$dest_folder/$filename"
        
        # اگر فایل وجود دارد، نام را تغییر می‌دهیم
        if [ -e "$dest" ]; then
            local timestamp=$(date +"%Y%m%d_%H%M%S")
            local name_without_ext="${filename%.*}"
            local ext="${filename##*.}"
            dest="$ARCHIVE_PATH/$dest_folder/${name_without_ext}_${timestamp}.${ext}"
        fi
        
        mv "$source" "$dest" 2>/dev/null
        if [ $? -eq 0 ]; then
            echo -e "  ${CYAN}✅ منتقل شد: $source -> $dest${NC}"
            return 0
        else
            echo -e "  ${YELLOW}⚠️  خطا در انتقال: $source${NC}"
            return 1
        fi
    else
        echo -e "  ${YELLOW}⚠️  فایل پیدا نشد: $source${NC}"
        return 1
    fi
}

# تابع برای انتقال پوشه
move_folder_to_archive() {
    local source="$1"
    local dest_folder="$2"
    
    if [ -d "$source" ]; then
        local foldername=$(basename "$source")
        local dest="$ARCHIVE_PATH/$dest_folder/$foldername"
        
        if [ -d "$dest" ]; then
            local timestamp=$(date +"%Y%m%d_%H%M%S")
            dest="$ARCHIVE_PATH/$dest_folder/${foldername}_${timestamp}"
        fi
        
        mv "$source" "$dest" 2>/dev/null
        if [ $? -eq 0 ]; then
            echo -e "  ${CYAN}✅ منتقل شد: $source -> $dest${NC}"
            return 0
        else
            echo -e "  ${YELLOW}⚠️  خطا در انتقال: $source${NC}"
            return 1
        fi
    else
        echo -e "  ${YELLOW}⚠️  پوشه پیدا نشد: $source${NC}"
        return 1
    fi
}

echo -e "${MAGENTA}🔄 شروع انتقال فایل‌ها...${NC}\n"

# ============================================
# دسته 1: فایل‌های Backup و Old
# ============================================
echo -e "${YELLOW}📦 دسته 1: فایل‌های Backup و Old${NC}"

backup_files=(
    "resources/views/home.blade.php.backup"
    "resources/views/welcome.blade.php.backup"
    "resources/views/home-old-backup.blade.php"
    "resources/views/welcome-old.blade.php"
    "resources/views/terms-old.blade.php"
    "resources/views/groups/index-old-backup.blade.php"
    "resources/views/invitation/index-old.blade.php"
    "resources/views/auth/login-old.blade.php"
    "resources/views/auth/register-old.blade.php"
    "resources/views/auth/register_step1_old_backup.blade.php"
    "resources/views/auth/register_step2_old_backup.blade.php"
    "resources/views/auth/register_step3_old_backup.blade.php"
)

for file in "${backup_files[@]}"; do
    move_to_archive "$file" "backup-old-files"
done

# ============================================
# دسته 2: فایل‌های موقت
# ============================================
echo -e "\n${YELLOW}📦 دسته 2: فایل‌های موقت${NC}"

temp_files=(
    "temp_old_chat.blade.php"
    "temp_location_original.blade.php"
    "f.blade.php"
    "dummy"
)

for file in "${temp_files[@]}"; do
    move_to_archive "$file" "temp-files"
done

# ============================================
# دسته 3: فایل‌های تست
# ============================================
echo -e "\n${YELLOW}📦 دسته 3: فایل‌های تست${NC}"

test_files=(
    "public/test-dark-mode.html"
    "public/test-encoding.php"
    "resources/views/test-design.blade.php"
    "resources/views/test-unified-layout.blade.php"
    "test_api_regions.php"
    "test_location_hierarchy.php"
)

for file in "${test_files[@]}"; do
    move_to_archive "$file" "test-files"
done

# ============================================
# دسته 4: فایل‌های SQL Backup
# ============================================
echo -e "\n${YELLOW}📦 دسته 4: فایل‌های SQL Backup${NC}"

sql_files=(
    "ybwztpvr_earth (7).sql"
    "import_locations_only.sql"
)

for file in "${sql_files[@]}"; do
    move_to_archive "$file" "sql-backups"
done

# ============================================
# دسته 5: فایل‌های Duplicate
# ============================================
echo -e "\n${YELLOW}📦 دسته 5: فایل‌های Duplicate${NC}"

duplicate_files=(
    "resources/views/home-new.blade.php"
    "resources/views/home-complete.blade.php"
    "resources/views/welcome-new.blade.php"
    "idex.js"
    "public/error_log"
)

for file in "${duplicate_files[@]}"; do
    move_to_archive "$file" "duplicate-files"
done

# ============================================
# دسته 6: پوشه __MACOSX
# ============================================
echo -e "\n${YELLOW}📦 دسته 6: پوشه __MACOSX${NC}"
move_folder_to_archive "__MACOSX" "macosx-folder"

# ============================================
# دسته 7: پوشه New ui
# ============================================
echo -e "\n${YELLOW}📦 دسته 7: پوشه New ui${NC}"
if [ -d "New ui" ]; then
    move_folder_to_archive "New ui" "new-ui-folder"
fi

# ============================================
# دسته 8: Migration Files .skip
# ============================================
echo -e "\n${YELLOW}📦 دسته 8: Migration Files .skip${NC}"

skip_files=(
    "database/migrations/2024_04_22_000001_create_reported_messages_table.php.skip"
    "database/migrations/2025_03_14_212321_add_description_to_groups_table.php.skip"
)

for file in "${skip_files[@]}"; do
    move_to_archive "$file" "migration-skip"
done

# ============================================
# دسته 9: اسکریپت‌های Check و Artisan
# ============================================
echo -e "\n${YELLOW}📦 دسته 9: اسکریپت‌های Check و Artisan${NC}"

check_scripts=(
    "check-user.php"
    "check_addresses_structure.php"
    "check_groups_encoding.php"
    "check_ids.php"
    "check_tehran_regions.php"
    "artisan-check-users-ids.php"
    "artisan-inspect-users.php"
    "artisan-scan-stock.php"
    "import_locations.php"
    "extract_location_data.php"
)

for file in "${check_scripts[@]}"; do
    move_to_archive "$file" "check-scripts"
done

# ============================================
# دسته 10: پوشه group-chat-redesign
# ============================================
echo -e "\n${YELLOW}📦 دسته 10: پوشه group-chat-redesign${NC}"
if [ -d "group-chat-redesign" ]; then
    move_folder_to_archive "group-chat-redesign" "new-ui-folder"
fi

# ============================================
# ایجاد فایل README در پوشه _archive
# ============================================
readme_content="# 📦 پوشه Archive - فایل‌های منتقل شده برای بررسی

این پوشه شامل فایل‌ها و پوشه‌هایی است که از پروژه اصلی منتقل شده‌اند تا قبل از حذف نهایی بررسی شوند.

## 📋 ساختار پوشه‌ها

- **backup-old-files/** - فایل‌های backup و old
- **temp-files/** - فایل‌های موقت
- **test-files/** - فایل‌های تست
- **sql-backups/** - فایل‌های SQL backup
- **duplicate-files/** - فایل‌های duplicate
- **macosx-folder/** - پوشه __MACOSX
- **new-ui-folder/** - پوشه New ui و group-chat-redesign
- **check-scripts/** - اسکریپت‌های check و import
- **migration-skip/** - فایل‌های migration با پسوند .skip

## ⚠️ هشدار

- این فایل‌ها **منتقل شده‌اند** نه حذف شده
- اگر مطمئن شدید که دیگر نیاز ندارید، می‌توانید حذف کنید
- اگر اشتباهی منتقل شده‌اند، می‌توانید به محل اصلی برگردانید

## 📅 تاریخ انتقال

تاریخ: $(date '+%Y-%m-%d %H:%M:%S')

## 🔄 برگرداندن فایل‌ها

اگر می‌خواهید فایلی را برگردانید، می‌توانید از دستور زیر استفاده کنید:

\`\`\`bash
# مثال: برگرداندن یک فایل
mv \"_archive/backup-old-files/home.blade.php.backup\" \"resources/views/home.blade.php.backup\"
\`\`\`

یا می‌توانید به صورت دستی فایل را کپی/جابجا کنید.

## 📝 یادداشت

برای جزئیات بیشتر، فایل \`CLEANUP_RECOMMENDATIONS.md\` را در ریشه پروژه بررسی کنید.
"

echo "$readme_content" > "$ARCHIVE_PATH/README.md"
echo -e "\n${GREEN}✅ فایل README.md در پوشه _archive ایجاد شد${NC}"

echo -e "\n${GREEN}✨ انتقال فایل‌ها با موفقیت انجام شد!${NC}\n"
echo -e "${CYAN}📁 تمام فایل‌ها در پوشه '$ARCHIVE_PATH' قرار دارند${NC}"
echo -e "${CYAN}📖 برای جزئیات بیشتر، فایل README.md در پوشه _archive را بخوانید${NC}\n"



