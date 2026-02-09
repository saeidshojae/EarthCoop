# اسکریپت PowerShell برای انتقال فایل‌های اضافی به پوشه _archive
# این اسکریپت فایل‌ها را حذف نمی‌کند، فقط به پوشه _archive منتقل می‌کند

# ایجاد پوشه _archive
$archivePath = "_archive"
if (-not (Test-Path $archivePath)) {
    New-Item -ItemType Directory -Path $archivePath | Out-Null
    Write-Host "Archive folder created: $archivePath" -ForegroundColor Green
}

# ایجاد زیرپوشه‌ها
$subFolders = @(
    "backup-old-files",
    "temp-files",
    "test-files",
    "sql-backups",
    "duplicate-files",
    "macosx-folder",
    "new-ui-folder",
    "check-scripts",
    "migration-skip"
)

foreach ($folder in $subFolders) {
    $fullPath = Join-Path $archivePath $folder
    if (-not (Test-Path $fullPath)) {
        New-Item -ItemType Directory -Path $fullPath | Out-Null
    }
}

Write-Host "Subfolders created" -ForegroundColor Green

# تابع برای انتقال فایل با حفظ ساختار
function Move-ToArchive {
    param(
        [string]$SourcePath,
        [string]$DestinationFolder,
        [string]$Description
    )
    
    if (Test-Path $SourcePath) {
        $fileName = Split-Path $SourcePath -Leaf
        $destPath = Join-Path $archivePath $DestinationFolder
        $destFile = Join-Path $destPath $fileName
        
        # اگر فایل وجود دارد، نام را تغییر می‌دهیم
        if (Test-Path $destFile) {
            $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
            $nameWithoutExt = [System.IO.Path]::GetFileNameWithoutExtension($fileName)
            $ext = [System.IO.Path]::GetExtension($fileName)
            $newName = "${nameWithoutExt}_${timestamp}${ext}"
            $destFile = Join-Path $destPath $newName
        }
        
        Move-Item -Path $SourcePath -Destination $destFile -Force
        Write-Host "  Moved: $SourcePath -> $destFile" -ForegroundColor Cyan
        return $true
    } else {
        Write-Host "  File not found: $SourcePath" -ForegroundColor Yellow
        return $false
    }
}

# تابع برای انتقال پوشه
function Move-FolderToArchive {
    param(
        [string]$SourcePath,
        [string]$DestinationFolder,
        [string]$Description
    )
    
    if (Test-Path $SourcePath) {
        $folderName = Split-Path $SourcePath -Leaf
        $destPath = Join-Path $archivePath $DestinationFolder
        $destFolder = Join-Path $destPath $folderName
        
        if (Test-Path $destFolder) {
            $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
            $destFolder = Join-Path $destPath "${folderName}_${timestamp}"
        }
        
        Move-Item -Path $SourcePath -Destination $destFolder -Force
        Write-Host "  Folder moved: $SourcePath -> $destFolder" -ForegroundColor Cyan
        return $true
    } else {
        Write-Host "  Folder not found: $SourcePath" -ForegroundColor Yellow
        return $false
    }
}

Write-Host ""
Write-Host "Starting file transfer..." -ForegroundColor Magenta
Write-Host ""

# ============================================
# Category 1: Backup and Old Files
# ============================================
Write-Host "Category 1: Backup and Old Files" -ForegroundColor Yellow

$backupFiles = @(
    "resources\views\home.blade.php.backup",
    "resources\views\welcome.blade.php.backup",
    "resources\views\home-old-backup.blade.php",
    "resources\views\welcome-old.blade.php",
    "resources\views\terms-old.blade.php",
    "resources\views\groups\index-old-backup.blade.php",
    "resources\views\invitation\index-old.blade.php",
    "resources\views\auth\login-old.blade.php",
    "resources\views\auth\register-old.blade.php",
    "resources\views\auth\register_step1_old_backup.blade.php",
    "resources\views\auth\register_step2_old_backup.blade.php",
    "resources\views\auth\register_step3_old_backup.blade.php"
)

foreach ($file in $backupFiles) {
    Move-ToArchive -SourcePath $file -DestinationFolder "backup-old-files" -Description "Backup or old file"
}

# ============================================
# Category 2: Temporary Files
# ============================================
Write-Host ""
Write-Host "Category 2: Temporary Files" -ForegroundColor Yellow

$tempFiles = @(
    "temp_old_chat.blade.php",
    "temp_location_original.blade.php",
    "f.blade.php",
    "dummy"
)

foreach ($file in $tempFiles) {
    Move-ToArchive -SourcePath $file -DestinationFolder "temp-files" -Description "Temporary file"
}

# ============================================
# Category 3: Test Files
# ============================================
Write-Host ""
Write-Host "Category 3: Test Files" -ForegroundColor Yellow

$testFiles = @(
    "public\test-dark-mode.html",
    "public\test-encoding.php",
    "resources\views\test-design.blade.php",
    "resources\views\test-unified-layout.blade.php",
    "test_api_regions.php",
    "test_location_hierarchy.php"
)

foreach ($file in $testFiles) {
    Move-ToArchive -SourcePath $file -DestinationFolder "test-files" -Description "Test file"
}

# ============================================
# Category 4: SQL Backup Files
# ============================================
Write-Host ""
Write-Host "Category 4: SQL Backup Files" -ForegroundColor Yellow

$sqlFiles = @(
    "ybwztpvr_earth (7).sql",
    "import_locations_only.sql"
)

foreach ($file in $sqlFiles) {
    Move-ToArchive -SourcePath $file -DestinationFolder "sql-backups" -Description "SQL backup file"
}

# ============================================
# Category 5: Duplicate Files
# ============================================
Write-Host ""
Write-Host "Category 5: Duplicate Files" -ForegroundColor Yellow

$duplicateFiles = @(
    "resources\views\home-new.blade.php",
    "resources\views\home-complete.blade.php",
    "resources\views\welcome-new.blade.php",
    "idex.js",
    "public\error_log"
)

foreach ($file in $duplicateFiles) {
    Move-ToArchive -SourcePath $file -DestinationFolder "duplicate-files" -Description "Duplicate file"
}

# ============================================
# Category 6: __MACOSX Folder
# ============================================
Write-Host ""
Write-Host "Category 6: __MACOSX Folder" -ForegroundColor Yellow
Move-FolderToArchive -SourcePath "__MACOSX" -DestinationFolder "macosx-folder" -Description "__MACOSX folder"

# ============================================
# Category 7: New ui Folder
# ============================================
Write-Host ""
Write-Host "Category 7: New ui Folder" -ForegroundColor Yellow
Move-FolderToArchive -SourcePath "New ui" -DestinationFolder "new-ui-folder" -Description "New ui folder"

# ============================================
# Category 8: Migration Files .skip
# ============================================
Write-Host ""
Write-Host "Category 8: Migration Files .skip" -ForegroundColor Yellow

$skipFiles = @(
    "database\migrations\2024_04_22_000001_create_reported_messages_table.php.skip",
    "database\migrations\2025_03_14_212321_add_description_to_groups_table.php.skip"
)

foreach ($file in $skipFiles) {
    Move-ToArchive -SourcePath $file -DestinationFolder "migration-skip" -Description "Skipped migration"
}

# ============================================
# Category 9: Check and Artisan Scripts
# ============================================
Write-Host ""
Write-Host "Category 9: Check and Artisan Scripts" -ForegroundColor Yellow

$checkScripts = @(
    "check-user.php",
    "check_addresses_structure.php",
    "check_groups_encoding.php",
    "check_ids.php",
    "check_tehran_regions.php",
    "artisan-check-users-ids.php",
    "artisan-inspect-users.php",
    "artisan-scan-stock.php",
    "import_locations.php",
    "extract_location_data.php"
)

foreach ($file in $checkScripts) {
    Move-ToArchive -SourcePath $file -DestinationFolder "check-scripts" -Description "Check or import script"
}

# ============================================
# Category 10: group-chat-redesign Folder
# ============================================
Write-Host ""
Write-Host "Category 10: group-chat-redesign Folder" -ForegroundColor Yellow
if (Test-Path "group-chat-redesign") {
    Move-FolderToArchive -SourcePath "group-chat-redesign" -DestinationFolder "new-ui-folder" -Description "group-chat-redesign folder"
}

# ============================================
# ایجاد فایل README در پوشه _archive
# ============================================
$readmeLines = @(
    "# Archive Folder - Moved Files for Review",
    "",
    "This folder contains files and folders that have been moved from the main project for review before final deletion.",
    "",
    "## Folder Structure",
    "",
    "- backup-old-files/ - Backup and old files",
    "- temp-files/ - Temporary files",
    "- test-files/ - Test files",
    "- sql-backups/ - SQL backup files",
    "- duplicate-files/ - Duplicate files",
    "- macosx-folder/ - __MACOSX folder",
    "- new-ui-folder/ - New ui and group-chat-redesign folders",
    "- check-scripts/ - Check and import scripts",
    "- migration-skip/ - Migration files with .skip extension",
    "",
    "## Warning",
    "",
    "- These files have been MOVED, not deleted",
    "- If you are sure they are not needed, you can delete them",
    "- If they were moved by mistake, you can restore them to their original location",
    "",
    "## Transfer Date",
    "",
    "Date: " + (Get-Date -Format "yyyy-MM-dd HH:mm:ss"),
    "",
    "## Restoring Files",
    "",
    "If you want to restore a file, you can use the following command:",
    "",
    "Move-Item `"_archive\backup-old-files\home.blade.php.backup`" `"resources\views\home.blade.php.backup`"",
    "",
    "Or you can manually copy/move the file.",
    "",
    "## Notes",
    "",
    "For more details, check the CLEANUP_RECOMMENDATIONS.md file in the project root."
)

$readmeContent = $readmeLines -join "`r`n"

$readmePath = Join-Path $archivePath "README.md"
$readmeContent | Out-File -FilePath $readmePath -Encoding UTF8
Write-Host ""
Write-Host "README.md file created in _archive folder" -ForegroundColor Green

Write-Host ""
Write-Host "Transfer completed successfully!" -ForegroundColor Green
Write-Host "All files are in the folder: $archivePath" -ForegroundColor Cyan
Write-Host "For more details, read the README.md file in the _archive folder" -ForegroundColor Cyan
Write-Host ""

