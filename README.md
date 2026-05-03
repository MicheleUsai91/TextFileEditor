# Dynamic TXT File Editor

A lightweight, high-performance web dashboard built in PHP, Vanilla JavaScript, and CSS for processing, enriching, and managing structured TXT and CSV files. 

This tool moves away from fragile "click and pray" scripts by providing a fully interactive user interface to safely manage data pipelines, validate inputs in real-time, and execute bulk operations with visual feedback.

## Features

* **Massive Bulk Processing:** Automatically process batches of TXT files and enrich them with corresponding CSV data in a single click.
* **Interactive Data Table:**
  * **Inline Editing:** Click directly on specific cells (e.g., `EER_ESITO`, `EER_NOTE_RIENTRO`) to edit them. The system automatically handles string-padding to preserve TXT structure upon saving.
  * **Smart Search & Sort:** Instantly filter rows by Pratica, Codice Fiscale, or Esito, and click column headers to sort data natively in the browser without reloading.
  * **Compact View:** Toggle a clean view that hides non-essential columns to reduce cognitive load.
* **Pipeline Management (Merge & Export):** Safely export modified data into structured TXT files, then selectively merge them into final, consolidated batches.
* **OS Integration:** Open working directories directly from the web browser and execute local PowerShell scripts (like Excel-to-CSV converters) with native buttons.

## Folder Structure & Workflow

The application relies on a strict directory pipeline to keep data organized. If a folder does not exist, the app will create it automatically when needed.

* `ORIGINALI/` - Drop your raw, unedited TXT files here for bulk processing.
* `ESITI/` - Drop your CSV enrichment files here.
* `DEFINITIVI/` - The destination for fully processed and enriched files after a bulk run.
* `MODIFICATI/` - When you edit data in the UI and click "Export TXT", the new structured files are saved here.
* `UNITI/` - The final destination. Files selected from the `MODIFICATI` folder are merged into consolidated files here.
* `CSV/` - Contains any table data exported as a standard CSV from the dashboard.

*Note: The root directory must also contain `Rules.csv` which dictates the parsing and padding schema for the TXT files.*

## Getting Started

### Prerequisites
* A local web server running **PHP 7.4+ or PHP 8.x** (e.g., XAMPP, Laragon, or PHP's built-in server).
* **Windows OS** is recommended if you intend to use the native folder-opening buttons and the `ExcelToCSV.ps1` PowerShell script.

### Installation
1. Clone or extract the project files into your local web server's document root (e.g., `htdocs` or `www`).
2. Ensure the web server has read/write permissions for the project folder so it can create the pipeline directories (`ORIGINALI`, `ESITI`, etc.).
3. Place your schema file (`Rules.csv`) in the root directory.
4. Open your web browser and navigate to `http://localhost/your-folder-name/index.php`.

## Usage

1. **Single File Mode:** Use the "Elaborazione Singola" card to manually upload a TXT file, view it in the table, optionally upload a CSV to complete missing columns, and edit the data inline.
2. **Batch Mode:** Drop your files into `ORIGINALI` and `ESITI`, then click **Lancia Elaborazione Massiva**. The system will process everything and output it to `DEFINITIVI`.
3. **Merging:** Use the "Unisci i file TXT" card to select files sitting in `MODIFICATI` and bind them together into a single file in `UNITI`.