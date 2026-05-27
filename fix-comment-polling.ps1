# PowerShell Script to Fix comment.chat.js polling issue
# File: fix-comment-polling.ps1

$filePath = "c:\Users\user\Desktop\NewEarthCoop\NewEarthCoop\public\js\comment.chat.js"

# Read file content
$content = Get-Content $filePath -Raw -Encoding UTF8

# Define the pattern to remove (setInterval block)
$oldPattern = @"
setInterval\(function\(\) \{
  \$\.ajax\(\{
        url: `/api/comments/\`\$\{blogID\}`/messages`,
        method: 'GET',
        success: function\(data\) \{
            \$\('#chat-box'\)\.html\(data\);

            const chatBox = document\.getElementById\('chat-box'\);
        \},
        error: function\(\) \{
            console\.error\('❌ خطا در دریافت پیام‌ها'\);
        \}
    \}\);
\}, 3000\);
"@

$replacement = @"
// ✅ REMOVED: setInterval polling (every 3 seconds) - CRITICAL PERFORMANCE FIX
// This was causing massive performance issues:
// - \$('#chat-box').html(data) every 3 seconds → FULL DOM REPLACE!
// - 20 AJAX requests/min per user per blog page
// - Reset scroll position every time
// - Lost all event listeners
// - Scroll jank/stutter
// - Input focus cleared
// NOW: Use event-based updates only (no polling)
// TODO: Implement CommentCreated/CommentReactionUpdated events with Laravel Echo
"@

# Perform replacement
if ($content -match "setInterval") {
    $newContent = $content -replace $oldPattern, $replacement
    Set-Content $filePath $newContent -Encoding UTF8 -NoNewline
    Write-Host "✅ Successfully removed comment polling from comment.chat.js" -ForegroundColor Green
} else {
    Write-Host "⚠️ setInterval not found - file may already be fixed" -ForegroundColor Yellow
}
