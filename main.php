<?php
session_start();

include './functions.php';

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
                $_SESSION['batch_result'] = ['success' => false, 'errors' => ['File Rules.csv non trovato nella cartella.']];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            $schema = loadSchemaFromCsv($csvPath);
            $txtDir = __DIR__ . DIRECTORY_SEPARATOR . 'TXT';
            
            if (!is_dir($txtDir)) {
                $_SESSION['batch_result'] = ['success' => false, 'errors' => ['Cartella TXT non trovata.']];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            $bulkDebugLogs = [];
            $allRows = processBulkTxtFiles($txtDir, $schema, $bulkDebugLogs);
            $_SESSION['bulk_debug_logs'] = $bulkDebugLogs;

            if (empty($allRows)) {
                $_SESSION['batch_result'] = ['success' => false, 'errors' => ['Nessun file .txt valido trovato nella cartella TXT.']];
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

// if ($txtFolderDebug) {
//     console_log($txtFolderDebug, 'TXT FOLDER DEBUG (Render)');
// }
// if ($bulkDebugLogs) {
//     console_log($bulkDebugLogs, 'PROCESS BULK TXT DEBUG (Render)');
// }