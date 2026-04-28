# PHP Dynamic TXT Editor

**PHP Dynamic TXT Editor** è un’applicazione web concepita per la gestione, l'analisi e l'arricchimento di flussi dati in formato testuale a lunghezza fissa. Il sistema permette di trasformare file TXT grezzi in tabelle interattive, validare la struttura dei record, integrare informazioni da fonti esterne (CSV) e generare nuovi file pronti per la produzione.

### Funzionalità Principali
- **Parsing Intelligente**: Estrazione automatica dei campi basata su uno schema posizionale predefinito.
- **Validazione in Tempo Reale**: Controllo immediato della lunghezza delle righe e dei singoli campi con segnalazione visiva degli errori.
- **Arricchimento Dati**: Integrazione di informazioni aggiuntive tramite caricamento di file CSV.
- **Gestione Esportazioni**: Generazione di file TXT conformi agli standard di lunghezza fissa e file CSV per analisi esterne.
- **Elaborazione Massiva**: Funzioni di merge e batch processing per gestire grandi volumi di file simultaneamente.

---

## 1. Funzionalità nel Dettaglio

### Estrazione e Padding
Il sistema applica automaticamente le regole di formattazione durante il parsing e l'arricchimento:
- **Campi Alfanumerici (A)**: Se la stringa è più corta del previsto, viene applicato un padding di spazi a destra.
- **Campi Numerici (S)**: Se il valore è più corto del previsto, viene applicato un padding di zeri a sinistra.

### Validazione e Visualizzazione
- Ogni riga viene analizzata singolarmente. Se una riga o un campo non corrispondono alla lunghezza definita dallo schema, il valore viene evidenziato in **rosso** all'interno della tabella HTML.
- **Nessun Troncamento**: Il sistema non taglia mai i dati in eccesso, permettendo all'utente di individuare anomalie nel file sorgente.

---

## 2. Specifiche dei File di Input

### File TXT (Dati)
- **Formato**: Lunghezza fissa (Fixed-width).
- **Vincolo Rigido**: Ogni riga deve contenere esattamente **4823 caratteri**.
- **Struttura**: Non sono presenti delimitatori; i campi sono determinati esclusivamente dalla loro posizione (indice di inizio e lunghezza).

### File CSV (Arricchimento)
- **Separatore**: Punto e virgola (`;`).
- **Codifica**: UTF-8.
- **Intestazioni Obbligatorie**: Il file deve contenere esattamente queste colonne:
  - `REESITO_PRATICA_CMP`: ID unico per il match con il file TXT.
  - `REESITO_ESITO_CONTATTO`: Valore da inserire nel campo `EER_ESITO`.
  - `REESITO_NOTA`: Valore da inserire nel campo `EER_NOTE_RIENTRO`.

#### Esempio di file CSV:
```csv
REESITO_PRATICA_CMP;REESITO_ESITO_CONTATTO;REESITO_NOTA
12345;OK;Pratica lavorata con successo
67890;KO;Cliente non raggiungibile
```

---

## 3. Flusso Operativo

### Fase 1: Caricamento e Visualizzazione
Caricare il file TXT tramite il modulo di upload. Il sistema elabora il file e genera una tabella HTML. Se vengono rilevate incongruenze nelle lunghezze, le celle interessate appariranno evidenziate.

### Fase 2: Arricchimento e Modifica
- **Integrazione CSV**: Caricare il file CSV per aggiornare automaticamente i campi di esito e nota sulle pratiche corrispondenti.
- **Eliminazione Massiva**: È possibile rimuovere righe dalla tabella selezionando le checkbox singole o inserendo una lista di ID separati da punto e virgola (es. `101;102;105`).

### Fase 3: Esportazione
- **Export CSV**: Scarica l'attuale visualizzazione della tabella in un file CSV (delimitato da `;`).
- **Export TXT**: Genera un nuovo file testuale in cui ogni riga è ricostruita secondo lo schema originale (4823 caratteri). **Nota**: l'export è bloccato se sono presenti righe con lunghezza totale errata.

### Fase 4: Operazioni Batch
- **Merge**: Selezione manuale di più file dalla cartella `EditedTXT/` per unirli in un unico file finale.
- **Elaborazione Massiva**: Esecuzione automatica che preleva tutti i file presenti in `TXT/`, li arricchisce con i dati presenti in `CSV/` e salva i risultati finali nella cartella `DEFINITIVI/`.

---

## 4. Output del Sistema

I file generati vengono organizzati come segue:
- **`EditedTXT/`**: Contiene i file TXT generati dall'esportazione singola o dal merge manuale.
- **`DEFINITIVI/`**: Destinazione dei file generati tramite elaborazione batch massiva.
- **Download Diretto**: I file CSV vengono inviati direttamente al browser senza salvataggio sul server.

---

## 5. Regole di Validazione e Sicurezza

- **Integrità del Record**: Se la ricostruzione di una riga non produce esattamente 4823 caratteri, il sistema interrompe l'esportazione per prevenire la corruzione dei dati.
- **Schema Posizionale**: La mappatura dei campi dipende dal file `Rules.csv` presente nella root o dallo schema hardcoded. Ogni scostamento tra il valore `Lungh` e i dati reali viene segnalato.
- **Gestione Sessione**: I dati caricati e le modifiche (arricchimento/eliminazioni) sono persistenti durante la sessione di lavoro dell'utente.

---

## 6. Requisiti di Sistema

- **PHP**: Versione 7.4 o superiore.
- **Estensioni**: `mbstring` (per la gestione corretta delle lunghezze stringa).
- **Permessi di Scrittura**: Necessari per le cartelle `EditedTXT/` e `DEFINITIVI/`.
- **Memoria**: Per file TXT estremamente grandi, regolare il parametro `memory_limit` in `php.ini`.