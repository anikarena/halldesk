# Build on this computer, upload, and put the release live on the server.
#   .\deploy\deploy.ps1            normal release (keeps all data; the first release loads the demo hall)
#   .\deploy\deploy.ps1 -Seed      wipe the database and load fresh demo data
#   .\deploy\deploy.ps1 -Setup     first time: install PHP and the nginx site
#   Set the server once:  $env:HALL_SERVER = 'root@your-server-ip'   (or pass -Server)
param(
    [string]$Server = $env:HALL_SERVER,
    [switch]$Setup,
    [switch]$Seed
)
if (-not $Server) { throw 'Set HALL_SERVER (for example root@203.0.113.10) or pass -Server.' }
# Native tools (npm, ssh) print normal progress on stderr, so do not stop on
# that; check each exit code instead.
$ErrorActionPreference = 'Continue'
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root

function Check([string]$step) {
    if ($LASTEXITCODE -ne 0) { throw "$step failed (exit $LASTEXITCODE)" }
}

cmd /c "npm run build 2>&1" | Select-Object -Last 1
Check 'Frontend build'

# Committed files plus the built assets (public/build is not in git)
$list = Join-Path $env:TEMP 'hall-files.txt'
$archive = Join-Path $env:TEMP 'hall.tgz'
git rev-parse --short HEAD | Out-File -Encoding ascii REVISION
@(git ls-files) + 'public/build' + 'REVISION' | Out-File -Encoding ascii $list
tar -czf $archive -T $list
Check 'Packing'
Remove-Item REVISION

ssh $Server 'mkdir -p /opt/hall-upload'
Check 'Connecting'
scp -q $archive deploy/release.sh deploy/server-setup.sh deploy/nginx-hall.conf "${Server}:/opt/hall-upload/"
Check 'Upload'
ssh $Server "sed -i 's/\r$//' /opt/hall-upload/*.sh /opt/hall-upload/nginx-hall.conf"

if ($Setup) {
    ssh $Server 'bash /opt/hall-upload/server-setup.sh' 2>&1 | ForEach-Object { "$_" }
    Check 'Server setup'
}

$seedFlag = if ($Seed) { 'SEED=1 ' } else { '' }
ssh $Server "${seedFlag}bash /opt/hall-upload/release.sh" 2>&1 | ForEach-Object { "$_" }
Check 'Release'
