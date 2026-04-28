<?php

include './main.php';

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic TXT File Editor</title>
    <link rel="stylesheet" href="./Style.css">
</head>
<body>

    <h2>PHP Dynamic TXT File Editor</h2>

    <div class="container">
        <h3>Elaborazione Massiva</h3>
        
        <?php if ($batchResult !== null): ?>
            <?php if ($batchResult['success']): ?>
                <div class="success-msg">
                    <strong>Success:</strong> <?php echo htmlspecialchars($batchResult['message']); ?>
                </div>
            <?php else: ?>
                <div class="error-msg">
                    <strong>Elaborazione Massiva ERRORE:</strong>
                    <ul style="margin-top: 5px; margin-bottom: 0;">
                        <?php foreach ($batchResult['errors'] as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- <?php if ($txtFolderDebug !== null): ?>
            <div class="debug-container">
                <h4>TXT FOLDER DEBUG:</h4>
                <p>Folder exists: <?php echo $txtFolderDebug['exists'] ? 'YES' : 'NO'; ?></p>
                <p>Readable: <?php echo $txtFolderDebug['readable'] ? 'YES' : 'NO'; ?></p>
                
                <h5>All files found:</h5>
                <pre><?php echo htmlspecialchars(implode("\n", $txtFolderDebug['all_files'] ?: ['(None)'])); ?></pre>
                
                <h5>Valid TXT files:</h5>
                <pre><?php echo htmlspecialchars(implode("\n", $txtFolderDebug['valid_txt'] ?: ['(None)'])); ?></pre>
            </div>
        <?php endif; ?> -->

        <!-- <?php if ($bulkDebugLogs !== null): ?>
            <div class="debug-container">
                <h4>PROCESS BULK TXT DEBUG:</h4>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($bulkDebugLogs as $log): ?>
                        <pre class="debug-log"><?php echo htmlspecialchars($log); ?></pre>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?> -->

        <form method="POST">
            <p style="font-size: 0.9em; color: #555; margin-top: 0;"><em>Unisce automaticamente tutti i file nella cartella <code>TXT</code>, li completa usando tutti i dati (nei file CSV) nella cartella <code>CSV</code>, ed esporta il risultato nella cartella <code>DEFINITIVI</code>.</em></p>
            <button type="submit" name="action" value="run_batch" class="btn-action">Lancia Elaborazione Massiva</button>
        </form>
    </div>

    <hr>

    <div class="container">
        <form method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 10px;">
                <h3 for="txt_file"><strong>Step 1: Carica il file TXT:</strong></h3>
                <!-- <br> -->
                <input type="file" name="txt_file" id="txt_file" accept=".txt" required>
                <!-- <p style="font-size: 0.9em; color: #555;"><em>Note: The schema will be automatically loaded from 'Rules.csv' located in the server directory. Uploading a new TXT file will reset the current view.</em></p> -->
            </div>
            <button type="submit" class="btn-action">Carica TXT</button>
            <?php if ($hasData && !empty($rows)): ?>
                <button type="submit" name="action" value="clear_session" formnovalidate class="btn-action">Ripulisci Dati</button>
                <button type="submit" name="action" value="export_csv" formnovalidate class="btn-action">Esporta in CSV</button>
            <?php endif; ?>
        </form>

        <?php if ($hasData && !empty($rows)): ?>
            <div class="deletion-box">
                <form method="POST">
                    <h3 for="manual_ids"><strong>Elimina righe da lista ID (EER_PRATICA_CMP):</strong></h3><br>
                    <input type="text" id="manual_ids" name="manual_ids" placeholder="Inserisci gli IDs separati da ;" style="width: 300px; padding: 4px;" required>
                    <button type="submit" name="action" value="delete_manual_ids" class="btn-action">Elimina per ID</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($hasData): ?>
        
        <!-- <?php if (!empty($debugLogs)): ?>
            <div class="debug-container">
                <h3>Enrichment Debug Logs</h3>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($debugLogs as $log): ?>
                        <pre class="debug-log"><?php echo htmlspecialchars($log); ?></pre>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?> -->

        <hr>
        
        <h3>Dati Elaborati</h3>
        <?php if (empty($rows)): ?>
            <p>Non ci sono dati validi da mostrare. La tabella è vuota.</p>
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
                <button type="submit" name="action" value="delete_selected_rows" style="margin-bottom: 30px;" class="btn-action">Elimina Righe Selezionate</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($rows)): ?>
            <div class="container">
                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 10px;">
                        <h3 for="csv_enrich_file"><strong>Step 2: Carica il file CSV degli esiti (Opzionale):</strong></h3>
                        <!-- <br> -->
                        <input type="file" name="csv_enrich_file" id="csv_enrich_file" accept=".csv" required>
                        <!-- <p style="font-size: 0.9em; color: #555;"><em>Upload a semicolon-separated (;) CSV file to enrich the currently displayed table data.</em></p> -->
                    </div>
                    <button type="submit" class="btn-action">Completa la Tabella</button>
                </form>
            </div>

            <div class="container">
                <h3>Step 3: Esporta in TXT</h3>
                
                <?php if ($exportResult !== null): ?>
                    <?php if ($exportResult['success']): ?>
                        <div class="success-msg">
                            <strong>Success:</strong> <?php echo htmlspecialchars($exportResult['message']); ?>
                        </div>
                    <?php else: ?>
                        <div class="error-msg">
                            <strong>Esportazione Fallita:</strong>
                            <ul style="margin-top: 5px; margin-bottom: 0;">
                                <?php foreach ($exportResult['errors'] as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST">
                    <!-- <p style="font-size: 0.9em; color: #555; margin-top: 0;"><em>Export the processed table back into a fixed-width format (4823 chars per line).</em></p> -->
                    <button type="submit" name="action" value="export_txt" class="btn-action">Export TXT</button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <hr>

    <div class="container">
        <h3>Unisci i file TXT</h3>
        
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
            <p style="font-size: 0.9em; color: #555;">Nessun file TXT trovato nella cartella <code>EditedTXT</code>.</p>
        <?php else: ?>
            <form method="POST">
                <p style="font-size: 0.9em; color: #555; margin-top: 0;"><em>Seleziona i file da unire.</em></p>
                <div class="file-list">
                    <?php foreach ($availableTxtFiles as $file): ?>
                        <label>
                            <input type="checkbox" name="merge_files[]" value="<?php echo htmlspecialchars($file); ?>">
                            <?php echo htmlspecialchars($file); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="action" value="merge_txt" class="btn-action">Unisci i file selezionati</button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>