<?php

define('EXPECTED_LINE_LENGTH', 4823);
define('CSV_SEPARATOR', ';');
define('COLONNA_ID_PRATICA','REESITO_PRATICA_CMP');
define('COLONNA_ESITO','REESITO_ESITO_CONTATTO');
define('COLONNA_NOTA','REESITO_NOTA');
define('CAMPO_ID_PRATICA', 'EER_PRATICA_CMP');
define('CAMPO_ESITO', 'EER_ESITO');
define('CAMPO_NOTA', 'EER_NOTE_RIENTRO');
define('REGOLA_POSIZIONE', 'Posizione');
define('REGOLA_ALIAS', 'Alias');
define('REGOLA_LUNGHEZZA', 'Lungh');
define('CARTELLA_ORIGINALI', 'ORIGINALI');
define('CARTELLA_ESITI', 'ESITI');
define('CARTELLA_DEFINITIVI', 'DEFINITIVI');
define('CARTELLA_UNITI', 'UNITI');
define('CARTELLA_MODIFICATI', 'MODIFICATI');
define('CARTELLA_CSV', 'CSV');
define('CARTELLA_EXCEL', 'EXCEL');

$message = '';
$messageType = '';

function loadSchemaFromCsv(string $filepath): array {
    if (!is_readable($filepath)) return [];
    $handle = fopen($filepath, 'r');
    if (!$handle) return [];
    $headers = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\');
    if ($headers === false) {
        fclose($handle); 
        return [];
    }
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    foreach ($headers as &$header) $header = trim($header);
    unset($header);
    $headersLen = count($headers);
    $schema = [];
    while (($row = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\')) !== false) {
        $hasData = false;
        foreach ($row as &$value) {
            $value = trim($value);
            if (!$hasData && $value) $hasData = true;
        }
        unset($value);
        if (!$hasData) continue;
        $schema[] = ($headersLen === count($row)) ? array_combine($headers, $row) : [];
    }
    fclose($handle);
    return $schema;
}

function loadEnrichmentDataFromCsv(string $filepath): array {
    if (!is_readable($filepath)) return [];
    $handle = fopen($filepath, 'r');
    if (!$handle) return [];
    $headers = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\');
    if ($headers === false) {
        fclose($handle);
        return [];
    }
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    foreach ($headers as &$h) $h = trim($h);
    unset($h);
    $colMap = [];
    foreach ([COLONNA_ID_PRATICA, COLONNA_ESITO, COLONNA_NOTA] as $col) {
        $idx = array_search($col, $headers);
        if ($idx === false) { fclose($handle); return []; }
        $colMap[$col] = $idx;
    }
    $idxId = $colMap[COLONNA_ID_PRATICA];
    $idxEs = $colMap[COLONNA_ESITO];
    $idxNo = $colMap[COLONNA_NOTA];
    $data = [];
    while (($row = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\')) !== false) {
        $rawKey = trim((string)($row[$idxId] ?? ''));
        if ($rawKey !== '') $data[(int)$rawKey] = ['ESITO' => trim((string)($row[$idxEs] ?? '')), 'NOTA' => trim((string)($row[$idxNo] ?? ''))];
    }
    fclose($handle);
    return $data;
}

function loadBulkEnrichmentData(string $csvDir): array {
    $data = [];
    if (!is_dir($csvDir)) return $data;
    $files = glob($csvDir . DIRECTORY_SEPARATOR . '*.csv');
    if (!$files) return $data;
    foreach ($files as $file) {
        if (!is_readable($file)) continue;
        $handle = fopen($file, 'r');
        if (!$handle) continue;
        $headers = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\');
        if ($headers === false) {
            fclose($handle);
            continue;
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        foreach ($headers as &$h) $h = trim($h);
        unset($h);
        $colMap = [];
        foreach ([COLONNA_ID_PRATICA, COLONNA_ESITO, COLONNA_NOTA] as $col) {
            $idx = array_search($col, $headers);
            if ($idx === false) { fclose($handle); continue 2; }
            $colMap[$col] = $idx;
        }
        $idxId = $colMap[COLONNA_ID_PRATICA];
        $idxEs = $colMap[COLONNA_ESITO];
        $idxNo = $colMap[COLONNA_NOTA];
        while (($row = fgetcsv($handle, 0, CSV_SEPARATOR, '"', '\\')) !== false) {
            $rawKey = trim((string)($row[$idxId] ?? ''));
            if ($rawKey !== '') $data[(int)$rawKey] = ['ESITO' => trim((string)($row[$idxEs] ?? '')), 'NOTA' => trim((string)($row[$idxNo] ?? ''))];
        }
        fclose($handle);
    }
    return $data;
}

function validateField(string $rawValue, array $schemaDef): array {
    $isA = ($schemaDef['Tipo'] ?? '') === 'A';
    return ['value' => str_pad($rawValue, (int)($schemaDef[REGOLA_LUNGHEZZA] ?? 0), $isA ? ' ' : '0', $isA ? STR_PAD_RIGHT : STR_PAD_LEFT)];
}

function parseLine(string $line, array $schema): array {
    $cleanLine = rtrim($line, "\r\n");
    $parsedData = [];
    foreach ($schema as $field) {
        if (!isset($field[REGOLA_POSIZIONE], $field[REGOLA_ALIAS], $field[REGOLA_LUNGHEZZA])) continue;
        $parsedData[$field[REGOLA_ALIAS]] = validateField((string)substr($cleanLine, (int)$field[REGOLA_POSIZIONE] - 1, (int)$field[REGOLA_LUNGHEZZA]), $field);
    }
    return ['data' => $parsedData];
}

function processTxtFile(string $txtFilepath, array $schema): array {
    if (!$schema || !is_readable($txtFilepath)) return ['rows' => [], 'headers' => []];
    $handle = fopen($txtFilepath, 'r');
    if (!$handle) return ['rows' => [], 'headers' => []];
    $parsedRows = [];
    while (($line = fgets($handle)) !== false) {
        if (trim($line) === '') continue;
        $data = parseLine($line, $schema)['data'] ?? [];
        if ($data) $parsedRows[] = ['columns' => $data];
    }
    fclose($handle);
    return ['rows' => $parsedRows, 'headers' => array_column($schema, REGOLA_ALIAS)];
}

function processBulkTxtFiles(string $txtDir, array $schema): array {
    $parsedRows = [];
    if (!$schema || !is_dir($txtDir)) return $parsedRows;
    $files = glob($txtDir . DIRECTORY_SEPARATOR . '*.txt');
    if (!$files) return $parsedRows;
    foreach ($files as $file) {
        if (!is_readable($file)) continue;
        $handle = fopen($file, 'r');
        if (!$handle) continue;
        while (($line = fgets($handle)) !== false) {
            if (trim($line) === '') continue;
            $data = parseLine($line, $schema)['data'] ?? [];
            if ($data) $parsedRows[] = ['columns' => $data];
        }
        fclose($handle);
    }
    return $parsedRows;
}

function enrichRows(array &$rows, array $enrichmentData, array $schema): void {
    if (!$enrichmentData || !$schema || !$rows) return;
    $schemaMap = array_column($schema, null, REGOLA_ALIAS);
    $sEsito = $schemaMap[CAMPO_ESITO] ?? null;
    $sNota = $schemaMap[CAMPO_NOTA] ?? null;
    if (!$sEsito && !$sNota) return;
    foreach ($rows as &$row) {
        $key = trim((string)($row['columns'][CAMPO_ID_PRATICA]['value'] ?? ''));
        if ($key === '' || !isset($enrichmentData[(int)$key])) continue;
        if ($sEsito) $row['columns'][CAMPO_ESITO] = validateField($enrichmentData[(int)$key]['ESITO'] ?? '', $sEsito);
        if ($sNota) $row['columns'][CAMPO_NOTA] = validateField($enrichmentData[(int)$key]['NOTA'] ?? '', $sNota);
    }
    unset($row);
}

function handleExport(array $rows, array $schema): array {
    if (!$rows) return ['success' => false, 'errors' => ['Nessun dato da esportare.']];
    $errors = []; $lines = []; $aliases = array_column($schema, REGOLA_ALIAS); $expLen = EXPECTED_LINE_LENGTH;
    foreach ($rows as $index => $row) {
        $line = ''; foreach ($aliases as $alias) $line .= $row['columns'][$alias]['value'] ?? '';
        $len = strlen($line);
        if ($len !== $expLen) $errors[] = "Line " . ($index + 1) . ": Lunghezza invalida. Attesa {$expLen}, ottenuta {$len}.";
        else $lines[] = $line;
    }
    if ($errors) return ['success' => false, 'errors' => $errors];
    $dir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_MODIFICATI;
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) return ['success' => false, 'errors' => ["Errore cartella: " . CARTELLA_MODIFICATI]];
    $filename = 'modificato_' . date('Ymd_His') . '.txt';
    if (file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, implode("\n", $lines)) === false) return ['success' => false, 'errors' => ["Errore scrittura: $filename"]];
    return ['success' => true, 'message' => "File creato: " . CARTELLA_MODIFICATI . "/$filename"];
}

function handleDefinitiviExport(array $rows, array $schema): array {
    if (!$rows) return ['success' => false, 'errors' => ['Nessun dato da esportare.']];
    $errors = []; $lines = []; $aliases = array_column($schema, REGOLA_ALIAS); $expLen = EXPECTED_LINE_LENGTH;
    foreach ($rows as $index => $row) {
        $line = ''; foreach ($aliases as $alias) $line .= $row['columns'][$alias]['value'] ?? '';
        $len = strlen($line);
        if ($len !== $expLen) $errors[] = "Line " . ($index + 1) . ": Lunghezza invalida. Attesa {$expLen}, ottenuta {$len}.";
        else $lines[] = $line;
    }
    if ($errors) return ['success' => false, 'errors' => $errors];
    $dir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_DEFINITIVI;
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) return ['success' => false, 'errors' => ["Errore cartella: " . CARTELLA_DEFINITIVI]];
    $filename = 'DEFINITIVI_' . date('Ymd_His') . '.txt';
    if (file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, implode("\n", $lines)) === false) return ['success' => false, 'errors' => ["Errore scrittura: $filename"]];
    return ['success' => true, 'message' => "File creato: " . CARTELLA_DEFINITIVI . "/$filename"];
}

function handleMerge(array $filesToMerge): array {
    if (!$filesToMerge) return ['success' => false, 'errors' => ['No files selected.']];
    $inDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_MODIFICATI;
    $outDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_UNITI;
    if (!is_dir($outDir) && !mkdir($outDir, 0777, true)) return ['success' => false, 'errors' => ["Errore cartella: " . CARTELLA_UNITI]];
    $validFiles = [];
    foreach ($filesToMerge as $f) {
        $name = basename($f); $path = $inDir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path) || !is_readable($path)) return ['success' => false, 'errors' => ["File danneggiato: $name"]];
        $validFiles[] = $path;
    }
    $outName = 'merged_' . date('Ymd_His') . '.txt';
    $outHandle = fopen($outDir . DIRECTORY_SEPARATOR . $outName, 'w');
    if (!$outHandle) return ['success' => false, 'errors' => ["Creazione file unito fallita."]];
    foreach ($validFiles as $file) {
        $inHandle = fopen($file, 'r');
        if (!$inHandle) { fclose($outHandle); return ['success' => false, 'errors' => ["Lettura fallita: " . basename($file)]]; }
        stream_copy_to_stream($inHandle, $outHandle);
        fclose($inHandle);
    }
    fclose($outHandle);
    return ['success' => true, 'message' => "File uniti con successo in: " . CARTELLA_UNITI . "/$outName"];
}

function deleteRowsByIds(array &$rows, array $idsToDelete, string $idType = CAMPO_ID_PRATICA): void {
    if (!$idsToDelete || !$rows) return;
    $lookup = array_flip($idsToDelete);
    foreach ($rows as $key => $row) {
        if (isset($lookup[trim((string)($row['columns'][$idType]['value'] ?? ''))])) unset($rows[$key]);
    }
    $rows = array_values($rows);
}