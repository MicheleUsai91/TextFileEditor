<?php include './main.php'; ?>

<!DOCTYPE html>
<html lang="it-IT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic TXT File Editor</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./Style.css">
    <script src="./Script.js"></script>
</head>
<body>
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
            <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-wrench"></i>
                <h3>Strumenti</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-stack">
                    <button type="submit" name="action" value="clear_session" formnovalidate class="btn btn-danger" title="Elimina tutti i dati in sessione">
                        <i class="fa-solid fa-trash-can"></i> Ripulisci Dati
                    </button>
                    <button type="submit" name="action" value="export_csv" formnovalidate class="btn btn-success" title="Esporta la tabella in formato CSV">
                        <i class="fa-solid fa-file-csv"></i> Esporta in CSV
                    </button>
                    <button type="submit" name="action" value="export_txt" class="btn btn-secondary" title="Esporta la tabella nel formato TXT strutturato">
                        <i class="fa-solid fa-file-lines"></i> Export TXT
                    </button>
                    <button type="button" id="btnOpenManual" class="btn btn-primary w-100" title="Apri il manuale delle istruzioni">
                        <i class="fa-solid fa-book-open"></i> Manuale d'Uso
                    </button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-gears"></i>
                <h3>Elaborazione Massiva</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <!-- ORIGINALI Folder -->
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-folder"></i> ORIGINALI</span>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="folder_target" value="ORIGINALI">
                                <button type="submit" name="action" value="open_folder" class="btn btn-secondary btn-sm" title="Apri cartella nel sistema"><i class="fa-solid fa-folder-open"></i></button>
                            </form>
                        </div>
                        <div class="file-list" style="max-height: 110px;">
                            <?php if (empty($batchTxtFiles)): ?> <span class="text-muted" style="font-size: 0.8rem;">Cartella vuota</span>
                            <?php else: foreach ($batchTxtFiles as $f): ?>
                                <div style="font-size: 0.8rem; margin-bottom: 3px;"><i class="fa-regular fa-file-text"></i> <?php echo htmlspecialchars($f); ?></div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- ESITI Folder -->
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-folder"></i> ESITI</span>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="folder_target" value="ESITI">
                                <button type="submit" name="action" value="open_folder" class="btn btn-secondary btn-sm" title="Apri cartella nel sistema"><i class="fa-solid fa-folder-open"></i></button>
                            </form>
                        </div>
                        <div class="file-list" style="max-height: 110px;">
                            <?php if (empty($batchCsvFiles)): ?> <span class="text-muted" style="font-size: 0.8rem;">Cartella vuota</span>
                            <?php else: foreach ($batchCsvFiles as $f): ?>
                                <div style="font-size: 0.8rem; margin-bottom: 3px;"><i class="fa-solid fa-file-csv"></i> <?php echo htmlspecialchars($f); ?></div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <button type="submit" name="action" value="run_batch" class="btn btn-primary w-100" title="Avvia il processo batch automatico">
                        <i class="fa-solid fa-play"></i> Lancia Elaborazione Massiva
                    </button>
                </form>
                <hr class="divider" style="margin: 5px 0;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-folder-check" style="color: var(--primary);"></i> File Definitivi</span>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="folder_target" value="DEFINITIVI">
                            <button type="submit" name="action" value="open_folder" class="btn btn-primary btn-sm" title="Apri cartella DEFINITIVI">
                                <i class="fa-solid fa-folder-open"></i>
                            </button>
                        </form>
                    </div>
                    <div class="file-list" style="max-height: 120px; background-color: #f8fafc;">
                        <?php if (empty($definitiviFiles)): ?>
                            <span class="text-muted" style="font-size: 0.8rem;">Nessun file definitivo presente.</span>
                        <?php else: ?>
                            <?php foreach ($definitiviFiles as $f): ?>
                                <div style="font-size: 0.8rem; margin-bottom: 5px; padding: 5px; border-radius: 4px; border: 1px solid var(--border-color); background: #fff; display: flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-file-lines text-muted"></i> <?php echo htmlspecialchars($f); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-file-import"></i>
                <h3>Elaborazione Singola</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="form-stack">
                    <div class="input-group">
                        <label for="txt_file">Carica il file TXT:</label>
                        <input type="file" name="txt_file" id="txt_file" accept=".txt" required class="file-input">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" title="Carica e processa un singolo file TXT">
                        <i class="fa-solid fa-upload"></i> Carica TXT
                    </button>
                </form>
                <hr class="divider">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-folder"></i> EXCEL</span>
                    <div style="display: flex; gap: 5px;">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="folder_target" value="EXCEL">
                            <button type="submit" name="action" value="open_folder" class="btn btn-secondary btn-sm" title="Apri cartella EXCEL">
                                <i class="fa-solid fa-folder-open"></i> Excel
                            </button>
                        </form>
                    </div>
                </div>
                <form method="POST" class="form-stack" style="margin-bottom: 20px;">
                    <div class="input-group">
                        <label>Converti Excel in CSV:</label>
                    </div>
                    <button type="submit" name="action" value="run_ps_script" class="btn btn-success w-100" title="Avvia la conversione PowerShell">
                        <i class="fa-brands fa-windows"></i> Lancia Script PowerShell
                    </button>
                </form>
                <?php if (!empty($rows)): ?>
                    <hr class="divider">
                    <form method="POST" enctype="multipart/form-data" class="form-stack">
                        <div class="input-group">
                            <label for="csv_enrich_file">CSV esiti (Opzionale):</label>
                            <input type="file" name="csv_enrich_file" id="csv_enrich_file" accept=".csv" required class="file-input">
                        </div>
                        <button type="submit" class="btn btn-secondary w-100" title="Arricchisci la tabella con i dati del CSV">
                            <i class="fa-solid fa-plus"></i> Completa la Tabella
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-object-group"></i>
            <h3>Unisci i file TXT</h3>
        </div>
        <div class="card-body" style="display: flex; flex-direction: column; gap: 15px;">
            
            <!-- Section 1: To Merge (MODIFICATI) -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-folder"></i> Da Unire (MODIFICATI)</span>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="folder_target" value="MODIFICATI">
                        <button type="submit" name="action" value="open_folder" class="btn btn-secondary btn-sm" title="Apri cartella MODIFICATI">
                            <i class="fa-solid fa-folder-open"></i>
                        </button>
                    </form>
                </div>

                <?php if (empty($availableTxtFiles)): ?>
                    <p class="text-muted" style="font-size: 0.85rem;"><i class="fa-solid fa-folder-open"></i> Nessun file in <code>MODIFICATI</code>.</p>
                <?php else: ?>
                    <form method="POST" class="form-stack">
                        <div class="file-list" style="max-height: 120px;">
                            <?php foreach ($availableTxtFiles as $file): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="merge_files[]" value="<?php echo htmlspecialchars($file); ?>">
                                    <i class="fa-regular fa-file-text"></i> <?php echo htmlspecialchars($file); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" name="action" value="merge_txt" class="btn btn-primary w-100" title="Unisci i file e salvali in UNITI">
                            <i class="fa-solid fa-link"></i> Unisci file selezionati
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <hr class="divider" style="margin: 5px 0;">

            <!-- Section 2: Already Merged (UNITI) -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-folder-check" style="color: var(--primary);"></i> File Uniti (UNITI)</span>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="folder_target" value="UNITI">
                        <button type="submit" name="action" value="open_folder" class="btn btn-primary btn-sm" title="Apri cartella UNITI">
                            <i class="fa-solid fa-folder-open"></i>
                        </button>
                    </form>
                </div>
                
                <div class="file-list" style="max-height: 120px; background-color: #f8fafc;">
                    <?php if (empty($mergedTxtFiles)): ?>
                        <span class="text-muted" style="font-size: 0.8rem;">Nessun file unito presente.</span>
                    <?php else: ?>
                        <?php foreach ($mergedTxtFiles as $f): ?>
                            <div style="font-size: 0.8rem; margin-bottom: 5px; padding: 5px; border-radius: 4px; border: 1px solid var(--border-color); background: #fff; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-file-lines text-muted"></i> <?php echo htmlspecialchars($f); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
    </div>
    <?php if ($hasData): ?>
        <div class="data-section">
            <div class="section-header">
                <h2><i class="fa-solid fa-table"></i> Dati Elaborati</h2>
            </div>
            <?php if (!empty($rows)): ?>
                <div class="deletion-box card">
                    <form method="POST" class="inline-form">
                        <div class="input-group-inline">
                            <label><i class="fa-solid fa-eraser"></i> Elimina da lista ID:</label>
                            <input type="text" id="manual_ids" name="manual_ids" class="text-input" placeholder="IDs separati da spazio" required>
                        </div>
                        <div class="radio-group">
                            <label class="radio-label"><input type="radio" name="delete_id_type" value="EER_PRATICA_CMP" checked> PRATICA_CMP</label>
                            <label class="radio-label"><input type="radio" name="delete_id_type" value="EER_N_PRATICA"> N_PRATICA</label>
                        </div>
                        <button type="submit" name="action" value="delete_manual_ids" class="btn btn-danger" title="Rimuovi massivamente gli ID inseriti">
                            <i class="fa-solid fa-trash"></i> Rimuovi
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <p>Non ci sono dati validi da mostrare. La tabella è vuota.</p>
                </div>
            <?php else: ?>
                <form method="POST" class="table-form">
                    <div class="table-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div style="display: flex; gap: 20px; align-items: center;">
                            <button type="submit" name="action" value="delete_selected_rows" class="btn btn-danger" title="Elimina le righe spuntate nella tabella">
                                <i class="fa-solid fa-trash-check"></i> Elimina Righe Selezionate
                            </button>
                            <div class="view-toggle">
                                <span class="text-muted"><i class="fa-solid fa-compress"></i> Vista Compatta</span>
                                <label class="switch" title="Mostra solo le colonne principali">
                                    <input type="checkbox" id="compactViewToggle" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                        <div class="input-group-inline">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                            <input type="text" id="tableSearch" class="text-input" placeholder="Cerca nella tabella per EER_N_PRATICA, EER_COD_FISC_CLI o EER_ESITO" style="width: 500px;">
                        </div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th class="col-checkbox"><i class="fa-solid fa-check-double"></i></th>
                                    <?php foreach ($headers as $header): ?>
                                        <th><?php echo htmlspecialchars($header); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php $praticaCmp = trim($row['columns'][CAMPO_ID_PRATICA]['value'] ?? ''); ?>
                                    <tr>
                                        <td class="col-checkbox">
                                            <?php if ($praticaCmp !== ''): ?>
                                                <input type="checkbox" name="selected_rows[]" class="row-checkbox" value="<?php echo htmlspecialchars($praticaCmp); ?>">
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ($headers as $header): ?>
                                            <?php $cellData = $row['columns'][$header] ?? ['value' => '']; ?>
                                            <td class="pre-wrap"><?php echo htmlspecialchars($cellData['value']); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div id="manualModal" class="modal-overlay hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin: 0; font-size: 1.25rem;"><i class="fa-solid fa-book-open"></i> Manuale d'Uso</h2>
                <button type="button" id="btnCloseManual" class="btn-close" title="Chiudi"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <?php
                    $manualPath = __DIR__ . DIRECTORY_SEPARATOR . 'manuale.html';
                    if (file_exists($manualPath)) {
                        include $manualPath;
                    } else {
                        echo '<p class="text-muted"><i class="fa-solid fa-triangle-exclamation"></i> Il file <code>manuale.html</code> non è stato trovato nella directory.</p>';
                    }
                ?>
            </div>
        </div>
    </div>
</body>
</html>