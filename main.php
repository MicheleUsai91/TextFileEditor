<?php
session_start();
include './functions.php';

$message = $_SESSION['message'] ?? null;
$messageType = $_SESSION['messageType'] ?? 'success';
unset($_SESSION['message'], $_SESSION['messageType']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redir = function () {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit; };

    if ($action === 'open_folder' && !empty($_POST['folder_target'])) {
        $target = $_POST['folder_target'];
        $allowed = ['ORIGINALI' => CARTELLA_ORIGINALI, 'ESITI' => CARTELLA_ESITI, 'MODIFICATI' => CARTELLA_MODIFICATI, 'UNITI' => CARTELLA_UNITI, 'CSV' => CARTELLA_CSV, 'EXCEL' => CARTELLA_EXCEL, 'DEFINITIVI' => CARTELLA_DEFINITIVI];
        if (isset($allowed[$target])) {
            $path = realpath(__DIR__ . DIRECTORY_SEPARATOR . $allowed[$target]);
            if ($path && is_dir($path)) {
                $os = strtoupper(substr(PHP_OS, 0, 3));
                if ($os === 'WIN')
                    pclose(popen('start explorer "' . $path . '"', 'r'));
                elseif ($os === 'DAR')
                    exec('open "' . $path . '" > /dev/null 2>&1 &');
                else
                    exec('xdg-open "' . $path . '" > /dev/null 2>&1 &');
            }
        }
        $redir();
    }

    if ($action === 'clear_session') {
        session_destroy();
        session_start();
        $_SESSION['message'] = 'Dati cancellati correttamente.';
        $_SESSION['messageType'] = 'success';
        $redir();
    }

    if ($action === 'run_ps_script') {
        $psPath = __DIR__ . DIRECTORY_SEPARATOR . 'ExcelToCSV.ps1';
        if (is_file($psPath)) {
            $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "' . $psPath . '"';
            exec($cmd . ' 2>&1', $output, $return_var);

            if ($return_var === 0) {
                $_SESSION['message'] = 'Script PowerShell eseguito con successo.';
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = 'Errore script: ' . implode(' ', $output);
                $_SESSION['messageType'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'File ExcelToCSV.ps1 non trovato.';
            $_SESSION['messageType'] = 'error';
        }
        $redir();
    }

    if ($action === 'export_csv' && isset($_SESSION['rows'], $_SESSION['headers'])) {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_CSV;
        if (!is_dir($dir))
            mkdir($dir, 0777, true);
        $filename = 'export_' . date('Ymd_His') . '.csv';
        $out = fopen($dir . DIRECTORY_SEPARATOR . $filename, 'w');
        fputcsv($out, $_SESSION['headers'], CSV_SEPARATOR, '"', '\\');
        foreach ($_SESSION['rows'] as $r) {
            $row = [];
            foreach ($_SESSION['headers'] as $h)
                $row[] = $r['columns'][$h]['value'] ?? '';
            fputcsv($out, $row, CSV_SEPARATOR, '"', '\\');
        }
        fclose($out);
        $_SESSION['message'] = "CSV salvato in: " . CARTELLA_CSV . "/$filename";
        $_SESSION['messageType'] = 'success';
        $redir();
    }

    if ($action === 'export_txt' && isset($_SESSION['rows'], $_SESSION['schema'])) {
        $res = handleExport($_SESSION['rows'], $_SESSION['schema']);
        $_SESSION['message'] = $res['success'] ? $res['message'] : implode(' ', $res['errors']);
        $_SESSION['messageType'] = $res['success'] ? 'success' : 'error';
        $redir();
    }

    if ($action === 'merge_txt') {
        $res = handleMerge($_POST['merge_files'] ?? []);
        $_SESSION['message'] = $res['success'] ? $res['message'] : implode(' ', $res['errors']);
        $_SESSION['messageType'] = $res['success'] ? 'success' : 'error';
        $redir();
    }

    if ($action === 'delete_manual_ids' && isset($_SESSION['rows']) && !empty($_POST['manual_ids'])) {
        deleteRowsByIds($_SESSION['rows'], preg_split('/\s+/', trim($_POST['manual_ids']), -1, PREG_SPLIT_NO_EMPTY), in_array($_POST['delete_id_type'] ?? '', ['EER_PRATICA_CMP', 'EER_N_PRATICA']) ? $_POST['delete_id_type'] : 'EER_PRATICA_CMP');
        $_SESSION['message'] = 'Eliminazione completata.';
        $_SESSION['messageType'] = 'success';
        $redir();
    }

    if ($action === 'delete_selected_rows' && isset($_SESSION['rows']) && !empty($_POST['selected_rows'])) {
        deleteRowsByIds($_SESSION['rows'], array_filter(array_map('trim', $_POST['selected_rows']), 'strlen'), 'EER_PRATICA_CMP');
        $_SESSION['message'] = 'Righe eliminate.';
        $_SESSION['messageType'] = 'success';
        $redir();
    }

    if ($action === 'run_batch') {
        $csv = __DIR__ . DIRECTORY_SEPARATOR . 'Rules.csv';
        $txtDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_ORIGINALI;
        if (!is_file($csv)) {
            $_SESSION['message'] = 'File Rules.csv non trovato.';
            $_SESSION['messageType'] = 'error';
            $redir();
        }
        if (!is_dir($txtDir)) {
            $_SESSION['message'] = 'Cartella ' . CARTELLA_ORIGINALI . ' non trovata.';
            $_SESSION['messageType'] = 'error';
            $redir();
        }

        $schema = loadSchemaFromCsv($csv);
        $rows = processBulkTxtFiles($txtDir, $schema);
        if (!$rows) {
            $_SESSION['message'] = 'Nessun dato valido in ' . CARTELLA_ORIGINALI;
            $_SESSION['messageType'] = 'error';
            $redir();
        }

        $enrichData = loadBulkEnrichmentData(__DIR__ . DIRECTORY_SEPARATOR . CARTELLA_ESITI);
        if ($enrichData)
            enrichRows($rows, $enrichData, $schema);

        $res = handleDefinitiviExport($rows, $schema);
        $_SESSION['schema'] = $schema;
        $_SESSION['headers'] = array_column($schema, 'Alias');
        $_SESSION['rows'] = $rows;
        $_SESSION['message'] = $res['success'] ? $res['message'] : implode(' ', $res['errors']);
        $_SESSION['messageType'] = $res['success'] ? 'success' : 'error';
        $redir();
    }

    if (isset($_FILES['txt_file']) && $_FILES['txt_file']['error'] === UPLOAD_ERR_OK && $_FILES['txt_file']['size'] > 0) {
        $csv = __DIR__ . DIRECTORY_SEPARATOR . 'Rules.csv';
        if (is_file($csv)) {
            $schema = loadSchemaFromCsv($csv);
            $res = processTxtFile($_FILES['txt_file']['tmp_name'], $schema);
            $_SESSION['schema'] = $schema;
            $_SESSION['headers'] = $res['headers'];
            $_SESSION['rows'] = $res['rows'];
            $_SESSION['message'] = 'TXT caricato correttamente.';
            $_SESSION['messageType'] = 'success';
        } else {
            $_SESSION['message'] = 'Rules.csv non trovato.';
            $_SESSION['messageType'] = 'error';
        }
        $redir();
    }

    if (isset($_FILES['csv_enrich_file'], $_SESSION['rows'], $_SESSION['schema']) && $_FILES['csv_enrich_file']['error'] === UPLOAD_ERR_OK && $_FILES['csv_enrich_file']['size'] > 0) {
        enrichRows($_SESSION['rows'], loadEnrichmentDataFromCsv($_FILES['csv_enrich_file']['tmp_name']), $_SESSION['schema']);
        $_SESSION['message'] = 'Dati CSV applicati.';
        $_SESSION['messageType'] = 'success';
        $redir();
    }

    $redir();
}

$hasData = isset($_SESSION['rows']);
$headers = $_SESSION['headers'] ?? [];
$rows = $_SESSION['rows'] ?? [];

$modificatiDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_MODIFICATI;
$originaliDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_ORIGINALI;
$esitiDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_ESITI;
$unitiDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_UNITI;
$definitiviDir = __DIR__ . DIRECTORY_SEPARATOR . CARTELLA_DEFINITIVI;

$availableTxtFiles = is_dir($modificatiDir) ? array_map('basename', glob($modificatiDir . DIRECTORY_SEPARATOR . '*.[tT][xX][tT]') ?: []) : [];
$batchTxtFiles = is_dir($originaliDir) ? array_map('basename', glob($originaliDir . DIRECTORY_SEPARATOR . '*.[tT][xX][tT]') ?: []) : [];
$batchCsvFiles = is_dir($esitiDir) ? array_map('basename', glob($esitiDir . DIRECTORY_SEPARATOR . '*.[cC][sS][vV]') ?: []) : [];
$mergedTxtFiles = is_dir($unitiDir) ? array_map('basename', glob($unitiDir . DIRECTORY_SEPARATOR . '*.[tT][xX][tT]') ?: []) : [];
$definitiviFiles = is_dir($definitiviDir) ? array_map('basename', glob($definitiviDir . DIRECTORY_SEPARATOR . '*.[tT][xX][tT]') ?: []) : [];