<#
.SYNOPSIS
    Compte les lignes de code du projet php-app.
.DESCRIPTION
    Scanne les fichiers source du projet, calcule le nombre de lignes par extension
    et affiche un récapitulatif détaillé avec le total global.
#>

$ErrorActionPreference = "SilentlyContinue"
$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..")

# Extensions de code/texte à analyser
$extensions = @(
    ".php", ".js", ".css", ".html", ".sql", ".sh",
    ".cmd", ".ps1", ".json", ".ini", ".md", ".yml", ".yaml"
)

# Dossiers à ignorer
$excludedDirs = @(
    ".git", ".tmp", ".vscode", "vendor", "node_modules", "vendor"
)

# Fichiers minifiés ou bibliothèques externes à ignorer
$excludedFiles = @(
    "vis-timeline.min.js",
    "vis-timeline.min.css"
)

$stats = @{}
foreach ($ext in $extensions) {
    $stats[$ext] = @{ Files = 0; Lines = 0 }
}

$allFiles = Get-ChildItem -Path $projectRoot -Recurse -File | Where-Object {
    $file = $_
    $relPath = $file.FullName.Substring($projectRoot.Path.Length)

    # Vérifier si dans un dossier exclu
    $inExcludedDir = $false
    foreach ($dir in $excludedDirs) {
        if ($relPath -match "[\\/]$([regex]::Escape($dir))[\\/]") {
            $inExcludedDir = $true
            break
        }
    }
    if ($inExcludedDir) { return $false }

    # Vérifier si fichier exclu
    if ($excludedFiles -contains $file.Name) { return $false }

    # Vérifier l'extension
    return $extensions -contains $file.Extension.ToLower()
}

$totalLines = 0
$totalFiles = 0

foreach ($file in $allFiles) {
    $ext = $file.Extension.ToLower()
    try {
        $content = Get-Content -Path $file.FullName
        $lines = if ($content -is [array]) { $content.Length } elseif ($null -ne $content) { 1 } else { 0 }
        $stats[$ext].Files++
        $stats[$ext].Lines += $lines
        $totalLines += $lines
        $totalFiles++
    } catch {
        # Fichier illisible ou binaire
    }
}

Write-Host ""
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "         COMPTEUR DE LIGNES DU PROJET             " -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ("{0,-12} | {1,10} | {2,12}" -f "Extension", "Fichiers", "Lignes")
Write-Host "--------------------------------------------------"

$sortedExts = $stats.Keys | Sort-Object { $stats[$_].Lines } -Descending

foreach ($ext in $sortedExts) {
    $fCount = $stats[$ext].Files
    $lCount = $stats[$ext].Lines
    if ($fCount -gt 0) {
        Write-Host ("{0,-12} | {1,10:N0} | {2,12:N0}" -f $ext, $fCount, $lCount)
    }
}

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ("{0,-12} | {1,10:N0} | {2,12:N0}" -f "TOTAL GLOBAL", $totalFiles, $totalLines) -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""
