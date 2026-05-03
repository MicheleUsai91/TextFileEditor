# 1. Temporarily bypass the execution policy for this specific PowerShell session
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force

# 2. Check if the module is installed WITHOUT trying to install it
$moduleName = "ImportExcel"
if (-not (Get-Module -ListAvailable -Name $moduleName)) {
    Write-Warning "The '$moduleName' module is not installed."
    Write-Host "$moduleName' will be installed." -ForegroundColor Green
    Install-Module -Name ImportExcel -Scope CurrentUser -Force
} else {
    Write-Host "Module '$moduleName' is installed. Proceeding with conversion..." -ForegroundColor Green
    Import-Module $moduleName
}

# 3. Define the folder containing your Excel files, and where to save the CSVs
$excelFolder = ".\EXCEL"   # Update this to your Excel folder path
$csvFolder = ".\ESITI"

# Create the output folder if it doesn't already exist
if (-not (Test-Path -Path $excelFolder)) {
    New-Item -ItemType Directory -Path $excelFolder | Out-Null
}

# 4. Grab all .xlsx files in the target directory
$excelFiles = Get-ChildItem -Path "$excelFolder\*" -Include *.xlsx, *.xls -File

if ($excelFiles.Count -eq 0) {
    Write-Host "No Excel files found in $excelFolder." -ForegroundColor Yellow
    exit
}

# 5. Loop through every file and convert it
foreach ($file in $excelFiles) {
    # Generate the new CSV filename and path
    $csvFileName = [System.IO.Path]::ChangeExtension($file.Name, ".csv")
    $csvFilePath = Join-Path -Path $csvFolder -ChildPath $csvFileName
    
    Write-Host "Converting: $($file.Name) -> $csvFileName..."
    
    try {
        # Import and Export with your specific formatting
        Import-Excel -Path $file.FullName | Export-Csv -Path $csvFilePath -Delimiter ';' -NoTypeInformation -Encoding UTF8
        Write-Host "  Success!" -ForegroundColor Green
    } 
    catch {
        Write-Error "  Failed to convert $($file.Name). Error: $_"
    }
}

Write-Host "All conversions finished!" -ForegroundColor Cyan