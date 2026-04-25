# PHP Fixed-Width TXT Visualizer
A standalone, single-file PHP web application designed to parse, visualize, enrich, and export fixed-width text files based on a predefined schema. This tool provides a clear interface for working with strict, schema-bound text documents without requiring a database or heavy frontend frameworks.
### Key Capabilities
 * Parse and visually validate strict fixed-width text files in the browser.
 * Enrich specific dataset fields in bulk using a delimited CSV file.
 * Export modified data back to a strictly validated fixed-width format.
 * Export data to a pipe-separated CSV for external analysis.
 * Merge multiple processed text files into a single consolidated file.
## Features
 * **Upload and Parse Fixed-Width TXT Files**: Reads files line-by-line, extracting fields strictly by schema position.
 * **Schema-Based Extraction**: Maps raw string indices to meaningful aliases based on a predefined structural schema.
 * **Automatic Padding**:
   * **Alphanumeric (A)**: Right-padded with spaces to match expected length.
   * **Numeric (S)**: Left-padded with zeros to match expected length.
 * **Conditional Validation Highlighting**: Visually flags fields in red if their extracted or enriched length does not match the schema's required length.
 * **CSV-Based Data Enrichment**: Updates target fields (EER_ESITO, EER_NOTE_RIENTRO) by matching records against an uploaded CSV file.
 * **TXT Export**: Reconstructs the table data back into a valid fixed-width text format.
 * **CSV Export**: Dynamically generates and downloads the current table state as a pipe-separated (|) CSV file.
 * **File Merging**: Select and concatenate multiple TXT files located in the output directory.
## Input File Specifications
### TXT File
 * Format: Fixed-width string.
 * Line Length: Every line must be exactly **4823 characters** long.
 * Delimiters: None.
 * Field Mapping: Defined by a positional schema indicating start index and length.
### CSV File (Enrichment)
 * Separator: Pipe (|)
 * Header Row: Required.
 * Required Headers:
   * REESITO_PRATICA_CMP (Used as the matching key)
   * REESITO_ESITO_CONTATTO
   * REESITO_NOTA
**Example:**
```csv
REESITO_PRATICA_CMP|REESITO_ESITO_CONTATTO|REESITO_NOTA
12345|OK|Sample note
67890|KO|Client unreachable

```
## Application Workflow
 1. **Upload TXT File**: Select and upload the raw fixed-width .txt file. The application will parse the file according to the schema and render it as an HTML table.
 2. **(Optional) Upload CSV**: Upload a | separated CSV file to enrich the currently displayed data. Matches are made using the CMP practice number.
 3. **Export Data**:
   * **TXT**: Validates the reconstructed lines and saves a compliant fixed-width file to the server.
   * **CSV**: Generates an immediate browser download of the table data.
 4. **Merge Files**: Scroll to the "Merge TXT Files" section to select and combine previously exported files from the EditedTXT folder.
## Output Behavior
### TXT Export
 * **Structure**: Reconstructed sequentially using the exact order defined in the schema.
 * **Constraints**: Each line is strictly enforced to be exactly **4823 characters**.
 * **Storage**: Saved server-side in the EditedTXT/ directory with a timestamped filename (e.g., output_YYYYMMDD_HHMMSS.txt).
### CSV Export
 * **Structure**: Generated dynamically for immediate download.
 * **Format**: Uses | as the column separator. Headers correspond to schema aliases.
### Merge Output
 * **Process**: Combines selected TXT files exactly as they are written, top to bottom.
 * **Storage**: Saved in the EditedTXT/ directory as merged_YYYYMMDD_HHMMSS.txt.
## Validation Rules
 * **Line Length**: Source and output TXT files must have lines of exactly **4823** characters.
 * **Field Length**: Extracted values must perfectly match the Lungh (length) property defined in the schema.
 * **No Truncation**: The application will not silently truncate oversized data; it will flag it for review.
 * **Visual Cues**: Invalid fields are rendered with red text in the HTML table.
 * **Export Blocking**: TXT export is strictly blocked if any reconstructed line fails the 4823-character length check.
## Project Structure
```text
/project-root
│── index.php       # Main application file (handles logic, UI, and processing)
│── Rules.csv       # Schema definition file
└── /EditedTXT      # Auto-generated directory for exported and merged TXT files

```
## Requirements
 * **PHP**: PHP 7.4 or higher (no frameworks required).
 * **Web Server**: Apache, Nginx, or the built-in PHP development server.
 * **File Permissions**: The web server user (e.g., www-data) must have write permissions to create and write to the EditedTXT directory.
## Limitations
 * **Single-File Architecture**: The entire application (HTML, PHP logic, processing) resides in a single file, prioritizing portability over modularity.
 * **No Client-Side Interactivity**: Relies entirely on server-side PHP processing; no JavaScript is used for table manipulation or async uploads.
 * **Schema Dependency**: Assumes a well-formed, predefined schema.
 * **Memory Constraints**: Processes files entirely in memory. Exceptionally large text files may exceed PHP memory_limit settings as no streaming optimization is implemented.
## Troubleshooting
 * **File not uploading**: Check your php.ini configuration for upload_max_filesize and post_max_size.
 * **Export fails**: Verify that every line reconstructs to exactly 4823 characters. Check the UI for any fields highlighted in red indicating a length mismatch.
 * **Merge not working**: Ensure the EditedTXT folder exists, has the correct write permissions, and contains valid .txt files.
 * **CSV not applied**: Verify that your CSV uses the | delimiter and that the required column headers exactly match REESITO_PRATICA_CMP, REESITO_ESITO_CONTATTO, and REESITO_NOTA without hidden BOM characters or trailing spaces.
## Future Improvements
 * **Streaming Processing**: Implement generator functions or stream handling to parse and write files line-by-line, drastically reducing memory footprint for massive files.
 * **UI Enhancements**: Add client-side JavaScript for sorting, filtering, and paginating the HTML table.
 * **Dynamic Schema Upload**: Allow the user to upload Rules.csv dynamically rather than reading it exclusively from the server directory.
 * **API Extraction**: Separate the parsing and validation logic into a RESTful API endpoint for headless operations.
