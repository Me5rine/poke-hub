param(
    [Parameter(Mandatory = $true)]
    [string]$ManifestPath,

    [Parameter(Mandatory = $true)]
    [string]$OutputRoot,

    [string]$GoTemplate = "",
    [string]$HomeTemplate = "",

    [int]$TimeoutSec = 30,
    [int]$RetryCount = 2,
    [switch]$SkipExisting
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Normalize-Bool {
    param([object]$Value)
    if ($null -eq $Value) { return $false }
    $v = "$Value".Trim().ToLowerInvariant()
    return @("1", "true", "yes", "y", "oui") -contains $v
}

function Normalize-Text {
    param([object]$Value)
    if ($null -eq $Value) { return "" }
    return "$Value".Trim()
}

function Normalize-SlugToken {
    param([object]$Value)
    $raw = Normalize-Text $Value
    if ($raw -eq "") { return "" }
    $token = $raw.ToLowerInvariant()
    $token = ($token -replace "[^a-z0-9\-]+", "-").Trim("-")
    return $token
}

function Add-Missing-Token {
    param(
        [string]$Stem,
        [string]$Token
    )

    if ([string]::IsNullOrWhiteSpace($Token)) {
        return $Stem
    }
    if ($Stem -match "(?:^|-)${Token}(?:-|$)") {
        return $Stem
    }
    return "$Stem-$Token"
}

function Build-ImageStem {
    param(
        [string]$Slug,
        [string]$Gender,
        [bool]$Shiny,
        [bool]$Gigamax,
        [bool]$Dynamax,
        [bool]$Mega,
        [bool]$Shadow,
        [bool]$Costume,
        [string]$Mode,
        [string]$FormSlug,
        [string]$CostumeSlug
    )

    $stem = Normalize-SlugToken $Slug
    if ([string]::IsNullOrWhiteSpace($stem)) {
        throw "Slug vide impossible a normaliser."
    }

    $modeNorm = Normalize-SlugToken $Mode
    $formNorm = Normalize-SlugToken $FormSlug
    $costumeNorm = Normalize-SlugToken $CostumeSlug

    $isGigamax = $Gigamax -or ($modeNorm -eq "gigantamax")
    $isDynamax = $Dynamax -or ($modeNorm -eq "dynamax")
    $isMega = $Mega -or ($modeNorm -eq "mega")
    $isShadow = $Shadow -or ($modeNorm -eq "shadow")
    $isCostume = $Costume -or ($modeNorm -eq "costume")

    if ($isGigamax -and -not $stem.StartsWith("gigantamax-")) {
        $stem = "gigantamax-$stem"
    } elseif ($isDynamax -and -not $stem.StartsWith("dynamax-")) {
        $stem = "dynamax-$stem"
    } elseif ($isMega -and -not $stem.StartsWith("mega-")) {
        $stem = "mega-$stem"
    }

    $g = Normalize-SlugToken $Gender
    if ($g -eq "male" -and $stem -notmatch "-male(?:-|$)") {
        $stem += "-male"
    } elseif ($g -eq "female" -and $stem -notmatch "-female(?:-|$)") {
        $stem += "-female"
    }

    if ($Shiny -and $stem -notmatch "-shiny(?:-|$)") {
        $stem += "-shiny"
    }

    if ($isShadow -and $stem -notmatch "-shadow(?:-|$)") {
        $stem += "-shadow"
    }

    if ($isCostume) {
        $stem = Add-Missing-Token -Stem $stem -Token "costume"
    }
    if ($costumeNorm -ne "") {
        $stem = Add-Missing-Token -Stem $stem -Token $costumeNorm
    }
    if ($formNorm -ne "") {
        $stem = Add-Missing-Token -Stem $stem -Token $formNorm
    }

    return $stem
}

function Resolve-SourceUrl {
    param(
        [pscustomobject]$Row,
        [string]$Template
    )

    $direct = Normalize-Text $Row.url
    if ($direct -ne "") {
        return $direct
    }

    if ([string]::IsNullOrWhiteSpace($Template)) {
        return ""
    }

    $dex = [int](Normalize-Text $Row.dex)
    $dex3 = "{0:d3}" -f $dex
    $slug = Normalize-Text $Row.slug
    $gender = Normalize-Text $Row.gender
    $form = Normalize-Text $Row.form
    $formSlug = Normalize-Text $Row.form_slug
    $costumeSlug = Normalize-Text $Row.costume_slug
    $mode = Normalize-Text $Row.mode
    $isShiny = Normalize-Bool $Row.is_shiny
    $isGigamax = Normalize-Bool $Row.is_gigamax
    $isDynamax = Normalize-Bool $Row.is_dynamax
    $isMega = Normalize-Bool $Row.is_mega
    $isShadow = Normalize-Bool $Row.is_shadow
    $isCostume = Normalize-Bool $Row.is_costume

    $stem = Build-ImageStem -Slug $slug -Gender $gender -Shiny:$isShiny -Gigamax:$isGigamax -Dynamax:$isDynamax -Mega:$isMega -Shadow:$isShadow -Costume:$isCostume -Mode $mode -FormSlug $formSlug -CostumeSlug $costumeSlug
    $genderSuffix = ""
    if ($gender -eq "male") { $genderSuffix = "-male" }
    if ($gender -eq "female") { $genderSuffix = "-female" }
    $shinySuffix = if ($isShiny) { "-shiny" } else { "" }
    $formSuffix = if ($form -ne "") { "-$($form.ToLowerInvariant())" } else { "" }
    $modePrefix = ""
    if ($mode -eq "gigantamax" -or $isGigamax) { $modePrefix = "gigantamax-" }
    elseif ($mode -eq "dynamax" -or $isDynamax) { $modePrefix = "dynamax-" }
    elseif ($mode -eq "mega" -or $isMega) { $modePrefix = "mega-" }
    $modeSuffix = ""
    if ($mode -eq "shadow" -or $isShadow) { $modeSuffix = "-shadow" }

    $url = $Template
    $url = $url.Replace("{dex}", "$dex")
    $url = $url.Replace("{dex3}", $dex3)
    $url = $url.Replace("{slug}", $slug)
    $url = $url.Replace("{stem}", $stem)
    $url = $url.Replace("{gender}", $gender)
    $url = $url.Replace("{gender_suffix}", $genderSuffix)
    $url = $url.Replace("{shiny_suffix}", $shinySuffix)
    $url = $url.Replace("{form}", $form)
    $url = $url.Replace("{form_suffix}", $formSuffix)
    $url = $url.Replace("{form_slug}", (Normalize-SlugToken $formSlug))
    $url = $url.Replace("{costume_slug}", (Normalize-SlugToken $costumeSlug))
    $url = $url.Replace("{mode}", (Normalize-SlugToken $mode))
    $url = $url.Replace("{mode_prefix}", $modePrefix)
    $url = $url.Replace("{mode_suffix}", $modeSuffix)

    return $url
}

function Invoke-DownloadWithRetry {
    param(
        [string]$Url,
        [string]$OutFile,
        [int]$TimeoutSec,
        [int]$RetryCount
    )

    $attempt = 0
    while ($true) {
        try {
            Invoke-WebRequest -Uri $Url -OutFile $OutFile -TimeoutSec $TimeoutSec -UserAgent "PokeHubImageSync/1.0"
            return $true
        } catch {
            $attempt++
            if ($attempt -gt $RetryCount) {
                Write-Warning "Echec telechargement: $Url -> $OutFile ($($_.Exception.Message))"
                return $false
            }
            Start-Sleep -Seconds 1
        }
    }
}

if (-not (Test-Path -LiteralPath $ManifestPath)) {
    throw "Manifest introuvable: $ManifestPath"
}

$rows = Import-Csv -LiteralPath $ManifestPath
if ($rows.Count -eq 0) {
    throw "Le manifest est vide: $ManifestPath"
}

$goDir = Join-Path $OutputRoot "pokemon-go/pokemon"
$homeDir = Join-Path $OutputRoot "home/pokemon"
New-Item -ItemType Directory -Force -Path $goDir | Out-Null
New-Item -ItemType Directory -Force -Path $homeDir | Out-Null

$ok = 0
$fail = 0
$skip = 0

foreach ($row in $rows) {
    $slug = Normalize-Text $row.slug
    if ($slug -eq "") {
        Write-Warning "Ligne ignoree (slug vide)."
        $fail++
        continue
    }

    $source = Normalize-Text $row.source
    $sourceNorm = $source.ToLowerInvariant()
    if ($sourceNorm -notin @("go", "home")) {
        Write-Warning "Source invalide '$source' pour slug '$slug' (attendu: go|home)."
        $fail++
        continue
    }

    $gender = Normalize-Text $row.gender
    $isShiny = Normalize-Bool $row.is_shiny
    $isGigamax = Normalize-Bool $row.is_gigamax
    $isDynamax = Normalize-Bool $row.is_dynamax
    $isMega = Normalize-Bool $row.is_mega
    $isShadow = Normalize-Bool $row.is_shadow
    $isCostume = Normalize-Bool $row.is_costume
    $mode = Normalize-Text $row.mode
    $formSlug = Normalize-Text $row.form_slug
    $costumeSlug = Normalize-Text $row.costume_slug

    $stem = Build-ImageStem -Slug $slug -Gender $gender -Shiny:$isShiny -Gigamax:$isGigamax -Dynamax:$isDynamax -Mega:$isMega -Shadow:$isShadow -Costume:$isCostume -Mode $mode -FormSlug $formSlug -CostumeSlug $costumeSlug
    $ext = Normalize-Text $row.extension
    if ($ext -eq "") { $ext = "png" }
    $ext = $ext.TrimStart(".").ToLowerInvariant()

    $dir = if ($sourceNorm -eq "go") { $goDir } else { $homeDir }
    $target = Join-Path $dir "$stem.$ext"

    if ($SkipExisting -and (Test-Path -LiteralPath $target)) {
        Write-Host "[SKIP] $target"
        $skip++
        continue
    }

    $template = if ($sourceNorm -eq "go") { $GoTemplate } else { $HomeTemplate }
    $url = Resolve-SourceUrl -Row $row -Template $template
    if ([string]::IsNullOrWhiteSpace($url)) {
        Write-Warning "Aucune URL resolue pour '$slug' (source=$sourceNorm)."
        $fail++
        continue
    }

    $success = Invoke-DownloadWithRetry -Url $url -OutFile $target -TimeoutSec $TimeoutSec -RetryCount $RetryCount
    if ($success) {
        Write-Host "[OK] $url -> $target"
        $ok++
    } else {
        $fail++
    }
}

Write-Host ""
Write-Host "Termine. OK=$ok | FAIL=$fail | SKIP=$skip"
