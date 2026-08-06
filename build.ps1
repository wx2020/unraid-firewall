# Build the noarch package used by unraid-firewall.plg.

$ErrorActionPreference = "Stop"
$version = (Get-Content -LiteralPath (Join-Path $PSScriptRoot "VERSION") -Raw).Trim()
$package = "unraid-firewall-$version-noarch-1.txz"

Remove-Item -LiteralPath (Join-Path $PSScriptRoot "build") -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath (Join-Path $PSScriptRoot $package), (Join-Path $PSScriptRoot "$package.sha256") -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path (Join-Path $PSScriptRoot "build") -Force | Out-Null

Push-Location (Join-Path $PSScriptRoot "src")
try {
    & tar -cJf (Join-Path $PSScriptRoot $package) .
    if ($LASTEXITCODE -ne 0) {
        Remove-Item -LiteralPath (Join-Path $PSScriptRoot $package) -Force -ErrorAction SilentlyContinue
        $sevenZip = Get-Command 7z, 7zz -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($null -eq $sevenZip) {
            throw "This Windows tar does not support xz. Install 7-Zip, use WSL, or run build.sh on Linux."
        }

        & $sevenZip.Source a -ttar -so package.tar . |
            & $sevenZip.Source a -si -txz (Join-Path $PSScriptRoot $package)
        if ($LASTEXITCODE -ne 0) { throw "7-Zip failed to create the xz package" }
    }
}
finally {
    Pop-Location
}

$hash = (Get-FileHash -LiteralPath (Join-Path $PSScriptRoot $package) -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  $package" | Out-File -LiteralPath (Join-Path $PSScriptRoot "$package.sha256") -Encoding ASCII

Write-Host "Package: $package"
Write-Host "SHA256: $hash"
Write-Host "Update the SHA256 in plugin/unraid-firewall.plg before publishing."
