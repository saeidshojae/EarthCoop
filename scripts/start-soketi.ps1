$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $repoRoot

$envFile = Join-Path $repoRoot '.env'
$config = @{}

if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match '^\s*([A-Z0-9_]+)=(.*)$') {
            $key = $matches[1]
            $value = $matches[2].Trim()
            if ($value.StartsWith('"') -and $value.EndsWith('"') -and $value.Length -ge 2) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            $config[$key] = $value
        }
    }
}

$appId = if ($config.ContainsKey('REVERB_APP_ID') -and $config['REVERB_APP_ID']) { $config['REVERB_APP_ID'] } else { 'app-id' }
$appKey = if ($config.ContainsKey('REVERB_APP_KEY') -and $config['REVERB_APP_KEY']) { $config['REVERB_APP_KEY'] } else { 'app-key' }
$appSecret = if ($config.ContainsKey('REVERB_APP_SECRET') -and $config['REVERB_APP_SECRET']) { $config['REVERB_APP_SECRET'] } else { 'app-secret' }
$reverbHost = if ($config.ContainsKey('REVERB_HOST') -and $config['REVERB_HOST']) { $config['REVERB_HOST'] } else { '127.0.0.1' }
$port = if ($config.ContainsKey('REVERB_PORT') -and $config['REVERB_PORT']) { $config['REVERB_PORT'] } else { '6001' }

try {
    $existing = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    if ($existing) {
        Write-Host "Soketi is already listening on $reverbHost`:$port."
        exit 0
    }
} catch {
    # If TCP lookup is unavailable, continue and let Soketi bind normally.
}

$localNode = Join-Path $repoRoot 'node_modules\node\bin\node.exe'
$soketiServer = Join-Path $repoRoot 'node_modules\@soketi\soketi\bin\server.js'

if (-not (Test-Path -LiteralPath $localNode) -or -not (Test-Path -LiteralPath $soketiServer)) {
    Write-Error 'Local realtime dependencies are missing. Run npm install before npm run realtime.'
    exit 1
}

Write-Host "Starting local Soketi on $reverbHost`:$port with pinned Node 18..."
& $localNode $soketiServer start --app-id $appId --app-key $appKey --app-secret $appSecret --host $reverbHost --port $port

exit $LASTEXITCODE
