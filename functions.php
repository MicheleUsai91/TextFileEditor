<?php

define('EXPECTED_LINE_LENGTH', 4823);
define('CSV_SEPARATOR', ';');

// --- Debug Function ---

// function console_log($data, ?string $label = null): void {
//     $json_data = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
//     echo "<script>\n";
//     if ($label !== null) {
//         $json_label = json_encode($label, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
//         echo "console.log({$json_label}, {$json_data});\n";
//     } else {
//         echo "console.log({$json_data});\n";
//     }
//     echo "</script>\n";
// }

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
        return ['success' => false, 'errors' => ['Nessun dato da esportare.']];
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
            $errors[] = "Line " . ($index + 1) . ": Lunghezza invalida. Attesa {$expectedLength}, ottenuta {$len}.";
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
            return ['success' => false, 'errors' => ["Errore creazione cartella: EditedTXT"]];
        }
    }

    $filename = 'output_' . date('Ymd_His') . '.txt';
    $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

    $handle = @fopen($filepath, 'w');
    if (!$handle) {
        return ['success' => false, 'errors' => ["Errore apertura file per scrittura: $filename"]];
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

    return ['success' => true, 'message' => "File creato con successo: EditedTXT/" . $filename];
}

function handleDefinitiviExport(array $rows, array $schema): array {
    if (empty($rows)) {
        return ['success' => false, 'errors' => ['Nessun dato da esportare.']];
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
            $errors[] = "Line " . ($index + 1) . ": Lunghezza invalida. Attesa {$expectedLength}, ottenuta {$len}.";
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
            return ['success' => false, 'errors' => ["Errore creazione cartella: DEFINITIVI"]];
        }
    }

    $filename = 'DEFINITIVI_' . date('Ymd_His') . '.txt';
    $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

    $handle = @fopen($filepath, 'w');
    if (!$handle) {
        return ['success' => false, 'errors' => ["Errore apertura file per scrittura: $filename"]];
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

    return ['success' => true, 'message' => "File creato con successo: DEFINITIVI/" . $filename];
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
            return ['success' => false, 'errors' => ["File non trovato o danneggiato: $filename"]];
        }
    }

    if (empty($validFiles)) {
         return ['success' => false, 'errors' => ['No valid files to merge.']];
    }

    $outFilename = 'merged_' . date('Ymd_His') . '.txt';
    $outPath = $dir . DIRECTORY_SEPARATOR . $outFilename;

    $outHandle = @fopen($outPath, 'w');
    if (!$outHandle) {
        return ['success' => false, 'errors' => ["Creazione file unito fallita."]];
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
            return ['success' => false, 'errors' => ["Lettura durante unione del file fallita: " . basename($inFile)]];
        }
    }
    fclose($outHandle);

    return ['success' => true, 'message' => "File uniti con successo: EditedTXT/" . $outFilename];
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