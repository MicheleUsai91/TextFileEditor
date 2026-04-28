<?php
session_start();

define('EXPECTED_LINE_LENGTH', 4823);
define('CSV_SEPARATOR', ';');

// --- Debug Function ---

function console_log($data, ?string $label = null): void {
    $json_data = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo "<script>\n";
    if ($label !== null) {
        $json_label = json_encode($label, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo "console.log({$json_label}, {$json_data});\n";
    } else {
        echo "console.log({$json_data});\n";
    }
    echo "</script>\n";
}

// --- Modular Functions ---

function loadSchemaFromCsv(string $filepath): array {
    $schema = [];
    $handle = @fopen($filepath, 'r');
    
    if (!$handle) {
        return [];
    }

    $headers = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\');
    if ($headers === false) {
        fclose($handle);
        return [];
    }

    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    $headers = array_map('trim', $headers);

    while (($row = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\')) !== false) {
        $row = array_map('trim', $row);
        
        if (empty(array_filter($row))) {
            continue;
        }

        if (count($headers) === count($row)) {
            $schema[] = array_combine($headers, $row);
        }
    }

    fclose($handle);
    return $schema;
}

function loadEnrichmentDataFromCsv(string $filepath): array {
    $data = [];
    $handle = @fopen($filepath, 'r');
    
    if (!$handle) {
        return [];
    }

    $headerLine = fgets($handle);
    if ($headerLine === false) {
        fclose($handle);
        return [];
    }

    $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', trim($headerLine));
    $headers = explode(CSV_SEPARATOR, $headerLine);
    $headers = array_map('trim', $headers);

    $requiredColumns = ['REESITO_PRATICA_CMP', 'REESITO_ESITO_CONTATTO', 'REESITO_NOTA'];
    $colMap = [];

    foreach ($requiredColumns as $col) {
        $idx = array_search($col, $headers);
        if ($idx === false) {
            fclose($handle);
            return []; 
        }
        $colMap[$col] = $idx;
    }

    while (($line = fgets($handle)) !== false) {
        $cleanLine = trim($line);
        if ($cleanLine === '') {
            continue;
        }

        $row = explode(CSV_SEPARATOR, $cleanLine);
        $rawKey = trim((string)($row[$colMap['REESITO_PRATICA_CMP']] ?? ''));
        
        if ($rawKey !== '') {
            $key = (int)$rawKey; 
            $data[$key] = [
                'ESITO' => trim((string)($row[$colMap['REESITO_ESITO_CONTATTO']] ?? '')),
                'NOTA' => trim((string)($row[$colMap['REESITO_NOTA']] ?? ''))
            ];
        }
    }

    fclose($handle);
    return $data;
}

function loadBulkEnrichmentData(string $csvDir): array {
    $data = [];
    if (!is_dir($csvDir)) {
        return $data;
    }

    $files = glob($csvDir . DIRECTORY_SEPARATOR . '*.csv');
    if (!$files) {
        return $data;
    }

    foreach ($files as $file) {
        $handle = @fopen($file, 'r');
        if (!$handle) {
            continue;
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);
            continue;
        }

        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', trim($headerLine));
        $headers = explode(CSV_SEPARATOR, $headerLine);
        $headers = array_map('trim', $headers);

        $requiredColumns = ['REESITO_PRATICA_CMP', 'REESITO_ESITO_CONTATTO', 'REESITO_NOTA'];
        $colMap = [];

        foreach ($requiredColumns as $col) {
            $idx = array_search($col, $headers);
            if ($idx === false) {
                fclose($handle);
                continue 2; 
            }
            $colMap[$col] = $idx;
        }

        while (($line = fgets($handle)) !== false) {
            $cleanLine = trim($line);
            if ($cleanLine === '') {
                continue;
            }

            $row = explode(CSV_SEPARATOR, $cleanLine);
            $rawKey = trim((string)($row[$colMap['REESITO_PRATICA_CMP']] ?? ''));
            
            if ($rawKey !== '') {
                $key = (int)$rawKey; 
                $data[$key] = [
                    'ESITO' => trim((string)($row[$colMap['REESITO_ESITO_CONTATTO']] ?? '')),
                    'NOTA' => trim((string)($row[$colMap['REESITO_NOTA']] ?? ''))
                ];
            }
        }
        fclose($handle);
    }
    return $data;
}

function validateField(string $rawValue, array $schemaDef): array {
    $lungh = (int)($schemaDef['Lungh'] ?? 0);
    $tipo = $schemaDef['Tipo'] ?? '';
    $originalLength = strlen($rawValue);
    $has_error = $originalLength !== $lungh;

    $value = $rawValue;

    if ($originalLength < $lungh) {
        if ($tipo === 'A') {
            $value = str_pad($value, $lungh, ' ', STR_PAD_RIGHT);
        } elseif ($tipo === 'S') {
            $value = str_pad($value, $lungh, '0', STR_PAD_LEFT);
        }
        $has_error = false; 
    }

    return [
        'value' => $value,
        'has_error' => $has_error
    ];
}

function parseLine(string $line, array $schema): array {
    $cleanLine = rtrim($line, "\r\n");
    $parsedData = [];

    foreach ($schema as $field) {
        if (!isset($field['Posizione'], $field['Alias'], $field['Lungh'])) {
            continue;
        }

        $posParts = explode('-', $field['Posizione']);
        if (count($posParts) !== 2) {
            continue;
        }

        $startIdx = (int)$posParts[0] - 1; 
        $length = (int)$field['Lungh'];

        $rawValue = substr($cleanLine, $startIdx, $length);
        if ($rawValue === false) {
            $rawValue = '';
        }

        $parsedData[$field['Alias']] = validateField($rawValue, $field);
    }

    return ['data' => $parsedData];
}

function processTxtFile(string $txtFilepath, array $schema): array {
    if (empty($schema)) {
        return ['rows' => [], 'headers' => []];
    }

    $handle = fopen($txtFilepath, 'r');
    if (!$handle) {
        return ['rows' => [], 'headers' => []];
    }

    $parsedRows = [];

    while (($line = fgets($handle)) !== false) {
        if (trim($line) === '' && feof($handle)) {
            continue;
        }

        $result = parseLine($line, $schema);

        if (!empty($result['data'])) {
            $parsedRows[] = ['columns' => $result['data']];
        }
    }

    fclose($handle);

    return [
        'rows' => $parsedRows,
        'headers' => array_column($schema, 'Alias')
    ];
}

function processBulkTxtFiles(string $txtDir, array $schema, array &$debugLogs = []): array {
    $debugLogs[] = "Entering processBulkTxtFiles()";
    $debugLogs[] = "Resolved TXT path: " . $txtDir;

    $isDir = is_dir($txtDir);
    $isReadable = is_readable($txtDir);
    
    $debugLogs[] = "Directory exists: " . ($isDir ? "YES" : "NO");
    $debugLogs[] = "Directory readable: " . ($isReadable ? "YES" : "NO");

    $parsedRows = [];
    
    if (!$isDir || !$isReadable) {
        $debugLogs[] = "Returning array with 0 elements";
        return $parsedRows;
    }

    $rawFiles = scandir($txtDir);
    if ($rawFiles === false) {
        $debugLogs[] = "ERROR: scandir() returned false.";
        $debugLogs[] = "Returning array with 0 elements";
        return $parsedRows;
    }

    $debugLogs[] = "Raw scandir output:\n" . implode("\n", $rawFiles);

    $validFiles = [];
    foreach ($rawFiles as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'txt') {
            $debugLogs[] = "Checking file: $file -> VALID TXT";
            $validFiles[] = $txtDir . DIRECTORY_SEPARATOR . $file;
        } else {
            $debugLogs[] = "Checking file: $file -> SKIPPED";
        }
    }

    foreach ($validFiles as $filepath) {
        $filename = basename($filepath);
        $debugLogs[] = "Reading file: $filename";

        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            $debugLogs[] = "ERROR: Unable to open file: $filename";
            continue;
        }

        $linesRead = 0;
        while (($line = fgets($handle)) !== false) {
            if (trim($line) === '' && feof($handle)) {
                continue;
            }

            $result = parseLine($line, $schema);

            if (!empty($result['data'])) {
                $parsedRows[] = ['columns' => $result['data']];
                $linesRead++;
            }
        }
        fclose($handle);
        $debugLogs[] = "Lines read: $linesRead";
    }

    $totalLines = count($parsedRows);
    $debugLogs[] = "Total merged lines: $totalLines";
    $debugLogs[] = "Returning array with $totalLines elements";

    return $parsedRows;
}

function enrichRows(array &$rows, array $enrichmentData, array $schema): array {
    $debugLogs = [];

    if (empty($enrichmentData) || empty($schema)) {
        return $debugLogs;
    }

    $schemaMap = [];
    foreach ($schema as $field) {
        if (isset($field['Alias'])) {
            $schemaMap[$field['Alias']] = $field;
        }
    }

    foreach ($rows as &$row) {
        $rawCmpKey = trim($row['columns']['EER_PRATICA_CMP']['value'] ?? '');
        
        if ($rawCmpKey !== '') {
            $cmpKey = (int)$rawCmpKey;
            
            $log = "MATCH CHECK:\nEER_PRATICA_CMP: {$cmpKey}\n";

            if (isset($enrichmentData[$cmpKey])) {
                $match = $enrichmentData[$cmpKey];
                
                $log .= "Match found: YES\n";
                $log .= "REESITO_ESITO_CONTATTO: {$match['ESITO']}\n";
                $log .= "REESITO_NOTA: {$match['NOTA']}\n";

                if (isset($schemaMap['EER_ESITO'])) {
                    $row['columns']['EER_ESITO'] = validateField($match['ESITO'], $schemaMap['EER_ESITO']);
                }
                
                if (isset($schemaMap['EER_NOTE_RIENTRO'])) {
                    $row['columns']['EER_NOTE_RIENTRO'] = validateField($match['NOTA'], $schemaMap['EER_NOTE_RIENTRO']);
                }
            } else {
                $log .= "Match found: NO\n";
            }
            
            $debugLogs[] = $log;
        }
    }

    return $debugLogs;
}

function handleExport(array $rows, array $schema): array {
    if (empty($rows)) {
        return ['success' => false, 'errors' => ['No data to export.']];
    }

    $errors = [];
    $linesToWrite = [];
    $expectedLength = EXPECTED_LINE_LENGTH;

    foreach ($rows as $index => $row) {
        $lineStr = '';
        foreach ($schema as $field) {
            if (isset($field['Alias'])) {
                $val = $row['columns'][$field['Alias']]['value'] ?? '';
                $lineStr .= $val;
            }
        }

        $len = strlen($lineStr);
        if ($len !== $expectedLength) {
            $errors[] = "Line " . ($index + 1) . ": Invalid length. Expected {$expectedLength}, got {$len}.";
        } else {
            $linesToWrite[] = $lineStr;
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'EditedTXT';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            return ['success' => false, 'errors' => ["Failed to create output directory: EditedTXT"]];
        }
    }

    $filename = 'output_' . date('Ymd_His') . '.txt';
    $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

    $handle = @fopen($filepath, 'w');
    if (!$handle) {
        return ['success' => false, 'errors' => ["Failed to open file for writing: $filename"]];
    }

    foreach ($linesToWrite as $line) {
        $count++;
        if ($count === count($linesToWrite)) {
            fwrite($handle, $line);
        } else {
            fwrite($handle, $line . "\n");
        }
    }
    fclose($handle);

    return ['success' => true, 'message' => "File successfully created: EditedTXT/" . $filename];
}

function handleDefinitiviExport(array $rows, array $schema): array {
    if (empty($rows)) {
        return ['success' => false, 'errors' => ['No data to export.']];
    }

    $errors = [];
    $linesToWrite = [];
    $expectedLength = EXPECTED_LINE_LENGTH;

    foreach ($rows as $index => $row) {
        $lineStr = '';
        foreach ($schema as $field) {
            if (isset($field['Alias'])) {
                $val = $row['columns'][$field['Alias']]['value'] ?? '';
                $lineStr .= $val;
            }
        }

        $len = strlen($lineStr);
        if ($len !== $expectedLength) {
            $errors[] = "Line " . ($index + 1) . ": Invalid length. Expected {$expectedLength}, got {$len}.";
        } else {
            $linesToWrite[] = $lineStr;
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'DEFINITIVI';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            return ['success' => false, 'errors' => ["Failed to create output directory: DEFINITIVI"]];
        }
    }

    $filename = 'DEFINITIVI_' . date('Ymd_His') . '.txt';
    $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

    $handle = @fopen($filepath, 'w');
    if (!$handle) {
        return ['success' => false, 'errors' => ["Failed to open file for writing: $filename"]];
    }

    foreach ($linesToWrite as $line) {
        $count++;
        if ($count === count($linesToWrite)) {
            fwrite($handle, $line);
        } else {
            fwrite($handle, $line . "\n");
        }
    }
    fclose($handle);

    return ['success' => true, 'message' => "File successfully created: DEFINITIVI/" . $filename];
}

function handleMerge(array $filesToMerge): array {
    if (empty($filesToMerge)) {
        return ['success' => false, 'errors' => ['No files selected for merging.']];
    }

    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'EditedTXT';
    $validFiles = [];
    foreach ($filesToMerge as $filename) {
        $filename = basename($filename);
        $filepath = $dir . DIRECTORY_SEPARATOR . $filename;
        if (is_file($filepath) && is_readable($filepath)) {
            $validFiles[] = $filepath;
        } else {
            return ['success' => false, 'errors' => ["File not found or unreadable: $filename"]];
        }
    }

    if (empty($validFiles)) {
         return ['success' => false, 'errors' => ['No valid files to merge.']];
    }

    $outFilename = 'merged_' . date('Ymd_His') . '.txt';
    $outPath = $dir . DIRECTORY_SEPARATOR . $outFilename;

    $outHandle = @fopen($outPath, 'w');
    if (!$outHandle) {
        return ['success' => false, 'errors' => ["Failed to create merged output file."]];
    }

    foreach ($validFiles as $inFile) {
        $inHandle = @fopen($inFile, 'r');
        if ($inHandle) {
            while (($line = fgets($inHandle)) !== false) {
                fwrite($outHandle, $line);
            }
            fclose($inHandle);
        } else {
            fclose($outHandle);
            return ['success' => false, 'errors' => ["Failed to read file during merge: " . basename($inFile)]];
        }
    }
    fclose($outHandle);

    return ['success' => true, 'message' => "Files successfully merged into: EditedTXT/" . $outFilename];
}

function deleteRowsByIds(array &$rows, array $idsToDelete): void {
    if (empty($idsToDelete) || empty($rows)) {
        return;
    }
    
    $lookup = array_flip($idsToDelete);
    
    $rows = array_filter($rows, function($row) use ($lookup) {
        $cmp = trim($row['columns']['EER_PRATICA_CMP']['value'] ?? '');
        return !isset($lookup[$cmp]);
    });
    
    $rows = array_values($rows);
}

function scanTxtFolderDebug(): array {
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'TXT';
    $debugInfo = [
        'path' => $dir,
        'exists' => is_dir($dir),
        'readable' => is_readable($dir),
        'all_files' => [],
        'valid_txt' => []
    ];

    if ($debugInfo['exists'] && $debugInfo['readable']) {
        $all = scandir($dir);
        foreach ($all as $file) {
            if ($file !== '.' && $file !== '..') {
                $debugInfo['all_files'][] = $file;
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'txt') {
                    $debugInfo['valid_txt'][] = $file;
                }
            }
        }
    }
    
    return $debugInfo;
}


// --- Main Controller ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        if ($_POST['action'] === 'clear_session') {
            session_destroy();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($_POST['action'] === 'export_csv') {
            if (isset($_SESSION['rows'], $_SESSION['headers'])) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="export.csv"');
                $output = fopen('php://output', 'w');
                fputcsv($output, $_SESSION['headers'], CSV_SEPARATOR);
                foreach ($_SESSION['rows'] as $row) {
                    $rowData = [];
                    foreach ($_SESSION['headers'] as $header) {
                        $rowData[] = $row['columns'][$header]['value'] ?? '';
                    }
                    fputcsv($output, $rowData, CSV_SEPARATOR);
                }
                fclose($output);
                exit;
            }
        }

        if ($_POST['action'] === 'export_txt') {
            if (isset($_SESSION['rows'], $_SESSION['schema'])) {
                $_SESSION['export_result'] = handleExport($_SESSION['rows'], $_SESSION['schema']);
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($_POST['action'] === 'merge_txt') {
            $filesToMerge = $_POST['merge_files'] ?? [];
            $_SESSION['merge_result'] = handleMerge($filesToMerge);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($_POST['action'] === 'delete_manual_ids') {
            if (isset($_SESSION['rows']) && !empty($_POST['manual_ids'])) {
                $rawIds = explode(';', $_POST['manual_ids']);
                $idsToDelete = array_filter(array_map('trim', $rawIds), function($v) { return $v !== ''; });
                deleteRowsByIds($_SESSION['rows'], $idsToDelete);
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($_POST['action'] === 'delete_selected_rows') {
            if (isset($_SESSION['rows']) && !empty($_POST['selected_rows'])) {
                $idsToDelete = array_filter(array_map('trim', $_POST['selected_rows']), function($v) { return $v !== ''; });
                deleteRowsByIds($_SESSION['rows'], $idsToDelete);
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($_POST['action'] === 'run_batch') {
            $txtFolderDebug = scanTxtFolderDebug();
            $_SESSION['txt_folder_debug'] = $txtFolderDebug;
            console_log($txtFolderDebug, 'TXT FOLDER DEBUG');

            $csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'Rules.csv';
            if (!file_exists($csvPath)) {
                $_SESSION['batch_result'] = ['success' => false, 'errors' => ['Schema file Rules.csv not found in root directory.']];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            $schema = loadSchemaFromCsv($csvPath);
            $txtDir = __DIR__ . DIRECTORY_SEPARATOR . 'TXT';
            
            if (!is_dir($txtDir)) {
                $_SESSION['batch_result'] = ['success' => false, 'errors' => ['TXT directory not found.']];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            $bulkDebugLogs = [];
            $allRows = processBulkTxtFiles($txtDir, $schema, $bulkDebugLogs);
            $_SESSION['bulk_debug_logs'] = $bulkDebugLogs;

            if (empty($allRows)) {
                $_SESSION['batch_result'] = ['success' => false, 'errors' => ['No valid TXT files or data found in TXT directory.']];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            $csvDir = __DIR__ . DIRECTORY_SEPARATOR . 'CSV';
            $enrichmentData = loadBulkEnrichmentData($csvDir);
            
            $debugLogs = enrichRows($allRows, $enrichmentData, $schema);
            $exportResult = handleDefinitiviExport($allRows, $schema);

            $_SESSION['schema'] = $schema;
            $_SESSION['headers'] = array_column($schema, 'Alias');
            $_SESSION['rows'] = $allRows;
            $_SESSION['debug_logs'] = $debugLogs;

            if ($exportResult['success']) {
                $_SESSION['batch_result'] = ['success' => true, 'message' => $exportResult['message']];
            } else {
                $_SESSION['batch_result'] = ['success' => false, 'errors' => $exportResult['errors']];
            }

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Step 1: Handle TXT Upload
    if (isset($_FILES['txt_file']) && $_FILES['txt_file']['error'] === UPLOAD_ERR_OK && $_FILES['txt_file']['size'] > 0) {
        $csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'Rules.csv';
        if (file_exists($csvPath)) {
            $schema = loadSchemaFromCsv($csvPath);
            $processResult = processTxtFile($_FILES['txt_file']['tmp_name'], $schema);
            
            $_SESSION['schema'] = $schema;
            $_SESSION['headers'] = $processResult['headers'];
            $_SESSION['rows'] = $processResult['rows'];
            unset($_SESSION['debug_logs']);
            unset($_SESSION['txt_folder_debug']);
            unset($_SESSION['bulk_debug_logs']);
        }
    }

    // Step 2: Handle CSV Enrichment Upload
    if (isset($_FILES['csv_enrich_file']) && $_FILES['csv_enrich_file']['error'] === UPLOAD_ERR_OK && $_FILES['csv_enrich_file']['size'] > 0) {
        if (isset($_SESSION['rows'], $_SESSION['schema'])) {
            $enrichmentData = loadEnrichmentDataFromCsv($_FILES['csv_enrich_file']['tmp_name']);
            $_SESSION['debug_logs'] = enrichRows($_SESSION['rows'], $enrichmentData, $_SESSION['schema']);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$hasData = isset($_SESSION['rows']);
$headers = $_SESSION['headers'] ?? [];
$rows = $_SESSION['rows'] ?? [];
$debugLogs = $_SESSION['debug_logs'] ?? [];

$exportResult = $_SESSION['export_result'] ?? null;
unset($_SESSION['export_result']);

$mergeResult = $_SESSION['merge_result'] ?? null;
unset($_SESSION['merge_result']);

$batchResult = $_SESSION['batch_result'] ?? null;
unset($_SESSION['batch_result']);

$txtFolderDebug = $_SESSION['txt_folder_debug'] ?? null;
$bulkDebugLogs = $_SESSION['bulk_debug_logs'] ?? null;

$editedTxtDir = __DIR__ . DIRECTORY_SEPARATOR . 'EditedTXT';
$availableTxtFiles = [];
if (is_dir($editedTxtDir)) {
    $files = glob($editedTxtDir . DIRECTORY_SEPARATOR . '*.txt');
    if ($files !== false) {
        foreach ($files as $file) {
            $availableTxtFiles[] = basename($file);
        }
    }
}

if ($txtFolderDebug) {
    console_log($txtFolderDebug, 'TXT FOLDER DEBUG (Render)');
}
if ($bulkDebugLogs) {
    console_log($bulkDebugLogs, 'PROCESS BULK TXT DEBUG (Render)');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Fixed-Width TXT File Visualizer</title>
    <style>
        body { font-family: sans-serif; line-height: 1.4; color: #333; margin: 20px; }
        .pre-wrap { white-space: pre; font-family: monospace; }
        .container { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background-color: #f9f9f9; }
        .debug-container { margin-bottom: 20px; padding: 15px; border: 1px solid #0056b3; background-color: #e6f2ff; }
        .debug-log { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #0056b3; }
        .debug-log:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .success-msg { color: #155724; margin-bottom: 15px; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; }
        .error-msg { color: #721c24; margin-bottom: 15px; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .btn-action { margin-left: 10px; padding: 5px 10px; }
        .file-list { max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; background: #fff; }
        .file-list label { display: block; margin-bottom: 5px; cursor: pointer; }
        .deletion-box { border-top: 1px solid #ccc; margin-top: 15px; padding-top: 15px; }
    </style>
</head>
<body>

    <h2>PHP Dynamic Fixed-Width TXT File Visualizer</h2>

    <div class="container">
        <h3>Batch Processing</h3>
        
        <?php if ($batchResult !== null): ?>
            <?php if ($batchResult['success']): ?>
                <div class="success-msg">
                    <strong>Success:</strong> <?php echo htmlspecialchars($batchResult['message']); ?>
                </div>
            <?php else: ?>
                <div class="error-msg">
                    <strong>Batch Processing Failed:</strong>
                    <ul style="margin-top: 5px; margin-bottom: 0;">
                        <?php foreach ($batchResult['errors'] as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($txtFolderDebug !== null): ?>
            <div class="debug-container">
                <h4>TXT FOLDER DEBUG:</h4>
                <p>Folder exists: <?php echo $txtFolderDebug['exists'] ? 'YES' : 'NO'; ?></p>
                <p>Readable: <?php echo $txtFolderDebug['readable'] ? 'YES' : 'NO'; ?></p>
                
                <h5>All files found:</h5>
                <pre><?php echo htmlspecialchars(implode("\n", $txtFolderDebug['all_files'] ?: ['(None)'])); ?></pre>
                
                <h5>Valid TXT files:</h5>
                <pre><?php echo htmlspecialchars(implode("\n", $txtFolderDebug['valid_txt'] ?: ['(None)'])); ?></pre>
            </div>
        <?php endif; ?>

        <?php if ($bulkDebugLogs !== null): ?>
            <div class="debug-container">
                <h4>PROCESS BULK TXT DEBUG:</h4>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($bulkDebugLogs as $log): ?>
                        <pre class="debug-log"><?php echo htmlspecialchars($log); ?></pre>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <p style="font-size: 0.9em; color: #555; margin-top: 0;"><em>Automatically merge all files from <code>TXT/</code>, enrich using all files from <code>CSV/</code>, and export the result to <code>DEFINITIVI/</code>.</em></p>
            <button type="submit" name="action" value="run_batch">Run Massive Elaboration</button>
        </form>
    </div>

    <hr>

    <div class="container">
        <form method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 10px;">
                <label for="txt_file"><strong>Step 1: Upload TXT File (4823 chars per line):</strong></label><br>
                <input type="file" name="txt_file" id="txt_file" accept=".txt" required>
                <p style="font-size: 0.9em; color: #555;"><em>Note: The schema will be automatically loaded from 'Rules.csv' located in the server directory. Uploading a new TXT file will reset the current view.</em></p>
            </div>
            <button type="submit">Upload and Parse TXT</button>
            <?php if ($hasData && !empty($rows)): ?>
                <button type="submit" name="action" value="clear_session" formnovalidate class="btn-action">Clear Data</button>
                <button type="submit" name="action" value="export_csv" formnovalidate class="btn-action">Export CSV</button>
            <?php endif; ?>
        </form>

        <?php if ($hasData && !empty($rows)): ?>
            <div class="deletion-box">
                <form method="POST">
                    <label for="manual_ids"><strong>Bulk Deletion by ID (EER_PRATICA_CMP):</strong></label><br>
                    <input type="text" id="manual_ids" name="manual_ids" placeholder="Enter IDs separated by ;" style="width: 300px; padding: 4px;" required>
                    <button type="submit" name="action" value="delete_manual_ids">Delete by IDs</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($hasData): ?>
        
        <?php if (!empty($debugLogs)): ?>
            <div class="debug-container">
                <h3>Enrichment Debug Logs</h3>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($debugLogs as $log): ?>
                        <pre class="debug-log"><?php echo htmlspecialchars($log); ?></pre>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <hr>
        
        <h3>Parsed Data</h3>
        <?php if (empty($rows)): ?>
            <p>No valid data rows found to display. The table is empty.</p>
        <?php else: ?>
            <form method="POST">
                <div style="overflow-x: auto; max-height: 500px; margin-bottom: 10px;">
                    <table border="1" cellpadding="5" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <?php foreach ($headers as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $praticaCmp = trim($row['columns']['EER_PRATICA_CMP']['value'] ?? ''); ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <?php if ($praticaCmp !== ''): ?>
                                            <input type="checkbox" name="selected_rows[]" value="<?php echo htmlspecialchars($praticaCmp); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($headers as $header): ?>
                                        <?php 
                                            $cellData = $row['columns'][$header] ?? ['value' => '', 'has_error' => true];
                                            $style = $cellData['has_error'] ? 'color: red;' : '';
                                        ?>
                                        <td class="pre-wrap" <?php if ($style) echo 'style="' . $style . '"'; ?>><?php echo htmlspecialchars($cellData['value']); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="action" value="delete_selected_rows" style="margin-bottom: 30px;">Delete Selected Rows</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($rows)): ?>
            <div class="container">
                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 10px;">
                        <label for="csv_enrich_file"><strong>Step 2: Upload CSV Enrichment File (Optional):</strong></label><br>
                        <input type="file" name="csv_enrich_file" id="csv_enrich_file" accept=".csv" required>
                        <p style="font-size: 0.9em; color: #555;"><em>Upload a semicolon-separated (;) CSV file to enrich the currently displayed table data.</em></p>
                    </div>
                    <button type="submit">Enrich Data</button>
                </form>
            </div>

            <div class="container">
                <h3>Step 3: Export to TXT</h3>
                
                <?php if ($exportResult !== null): ?>
                    <?php if ($exportResult['success']): ?>
                        <div class="success-msg">
                            <strong>Success:</strong> <?php echo htmlspecialchars($exportResult['message']); ?>
                        </div>
                    <?php else: ?>
                        <div class="error-msg">
                            <strong>Export Failed:</strong>
                            <ul style="margin-top: 5px; margin-bottom: 0;">
                                <?php foreach ($exportResult['errors'] as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST">
                    <p style="font-size: 0.9em; color: #555; margin-top: 0;"><em>Export the processed table back into a fixed-width format (4823 chars per line).</em></p>
                    <button type="submit" name="action" value="export_txt">Export TXT</button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <hr>

    <div class="container">
        <h3>Merge TXT Files</h3>
        
        <?php if ($mergeResult !== null): ?>
            <?php if ($mergeResult['success']): ?>
                <div class="success-msg">
                    <strong>Success:</strong> <?php echo htmlspecialchars($mergeResult['message']); ?>
                </div>
            <?php else: ?>
                <div class="error-msg">
                    <strong>Merge Failed:</strong>
                    <ul style="margin-top: 5px; margin-bottom: 0;">
                        <?php foreach ($mergeResult['errors'] as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($availableTxtFiles)): ?>
            <p style="font-size: 0.9em; color: #555;">No TXT files found in the <code>EditedTXT</code> directory.</p>
        <?php else: ?>
            <form method="POST">
                <p style="font-size: 0.9em; color: #555; margin-top: 0;"><em>Select files to merge (order of selection corresponds to top-to-bottom reading).</em></p>
                <div class="file-list">
                    <?php foreach ($availableTxtFiles as $file): ?>
                        <label>
                            <input type="checkbox" name="merge_files[]" value="<?php echo htmlspecialchars($file); ?>">
                            <?php echo htmlspecialchars($file); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="action" value="merge_txt">Merge Selected Files</button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>