<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;

class FileService
{
    private $cache = [];

    public function loadSpreadsheetData($filePath)
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);  // Charge le fichier
        $sheet = $spreadsheet->getActiveSheet(); // Récupère la feuille active
        $rows = [];  // Tableau pour stocker les lignes
        // Parcours des lignes du fichier
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Permet d'itérer sur toutes les cellules, même vides
    
            $rowData = array(); // Tableau pour stocker les données de chaque ligne
    
            // Parcours des cellules de la ligne
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getFormattedValue();  // Ajoute la valeur de la cellule au tableau de la ligne
            }
            $rowTMP = array();
            $rowTMP['RowData'] = $rowData;
            $rowTMP['State'] = 'OK';
            // Ajoute cette ligne à la collection de lignes avec une clé 'RowData' et une clé 'State'
            $rows[] = $rowTMP ;
        
        }
        return $rows;  // Retourne les lignes formatées
    }

    public function validateFileExtension(UploadedFile $file): bool
    {
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $validExtensions = ['csv', 'xls', 'xlsx'];

        if (!in_array($fileExtension, $validExtensions)) {
            return false;
        }

        $mimeType = $file->getMimeType();
        $validMimeTypes = [
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        return in_array($mimeType, $validMimeTypes);
    }

    public function storeDataInSession(array $data, SessionInterface $session): void
    {
        // Pas besoin de reformatter les données, nous utilisons le format déjà correct
        $session->set('filtered_data', $data);
    }

    // Retrieving data from session
    public function getDataFromSession(SessionInterface $session)
    {
        $data = $session->get('filtered_data', []);

        // Aucune manipulation nécessaire ici, retourne simplement les données
        return $data;
    }

    public function ignoreFirstRows(array $data, string $ignoreOption): array
    {
        if ($ignoreOption === 'one' && count($data) > 0) {
            array_shift($data);
        } elseif ($ignoreOption === 'two' && count($data) > 1) {
            array_shift($data);
            array_shift($data);
        }

        return $data;
    }

    public function getColumnLetters(array $data): array
    {
        $numColumns = count($data[0]['RowData']);
        $letters = [];
        for ($i = 0; $i < $numColumns; $i++) {
            $letters[] = chr(65 + $i);
        }

        return $letters;
    }

    public function filterDataBySelectedColumns(array $data, array $selectedColumns): array
    {
        $filteredData = [];
        if (!empty($selectedColumns)) {
            foreach ($data as $row) {
                $filteredRow = [];
                foreach ($selectedColumns as $col) {
                    $colIndex = ord($col) - 65;
                    $filteredRow[] = $row['RowData'][$colIndex] ?? '';
                }
                $filteredData[] = $filteredRow;
            }
        } else {
            $filteredData = $data;
        }

        return $filteredData;
    }

    public function filterDataByColumns(array $data, array $selectedColumns): array
    {
        return array_map(function ($row) use ($selectedColumns) {
            return array_filter($row['RowData'], function ($key) use ($selectedColumns) {
                return in_array($key, $selectedColumns);
            }, ARRAY_FILTER_USE_KEY);
        }, $data);
    }

    public function generateCsvFromFilteredData(array $filteredData, array $selectedColumns): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $selectedColumns);

        foreach ($filteredData as $row) {
            fputcsv($handle, $row['RowData']);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $csvContent;
    }

    public function generateCsvFromData(array $data): string
    {
        $csvContent = '';
        $headers = array_keys($data[0]['RowData']);
        $csvContent .= implode(';', $headers) . "\n";

        foreach ($data as $row) {
            $csvContent .= implode(';', $row['RowData']) . "\n";
        }

        return $csvContent;
    }

    public function prepareCsvData(array $selectedColumns, array $filteredData): array
    {
        $csvData = [];

        // Traiter les colonnes sélectionnées et les organiser
        foreach ($selectedColumns as $colTitle => $colLetters) {
            if ($colLetters) {
                if ($colTitle === 'Civilité, Nom, Prénom') {
                    // Si c'est la liste multiple, on sépare les colonnes pour chaque sélection
                    $letters = explode(',', $colLetters); // Ex: ['A', 'B', 'C']
                    foreach ($letters as $index => $letter) {
                        // Si c'est la première, on met le titre, sinon on laisse l'en-tête vide
                        $csvData[0][$colTitle . ($index > 0 ? ' - ' . ($index + 1) : '')] = $letter;
                    }
                } else {
                    // Pour les autres, on récupère la donnée et on l'ajoute au CSV
                    $csvData[0][$colTitle] = $colLetters;
                }
            } else {
                // Si la colonne n'a pas été sélectionnée, on ajoute une colonne vide
                $csvData[0][$colTitle] = '';
            }
        }
    
        // Ajouter les données dans les bonnes colonnes (par lettre de colonne)
        foreach ($filteredData as $rowIndex => $row) {
            foreach ($selectedColumns as $colTitle => $colLetters) {
                // Assure-toi de l'indexation correcte
                $columnIndex = array_search($colLetters, array_column($filteredData[0], 0)); // Trouver l'indice de la colonne
                if (isset($row[$columnIndex])) {
                    $csvData[$rowIndex + 1][$colTitle] = $row[$columnIndex];
                } else {
                    $csvData[$rowIndex + 1][$colTitle] = ''; // Ajouter une cellule vide si aucune donnée n'est présente
                }
            }
        }
        return $csvData;
    }
    

    public function getColumnData(array $data, array $selectedColumns): array
    {
        $result = [];
        foreach ($selectedColumns as $column) {
            if (empty($column)) {
                $result[] = [];
            } else {
                $colIndex = ord(strtoupper($column)) - 65;
                $columnData = [];

                foreach ($data as $row) {
                    $columnData[] = $row['RowData'][$colIndex] ?? '';
                }

                $result[] = $columnData;
            }
        }
        return $result;
    }

    public function splitColumnData(array $filteredData, array $columnsToSplit): array
    {
        foreach ($filteredData as &$row) {
            foreach ($columnsToSplit as $columnName => $splitIndexes) {
                if (isset($row['RowData'][$columnName])) {
                    $splitData = explode(' ', $row['RowData'][$columnName]);
                    foreach ($splitIndexes as $index => $splitColumn) {
                        $row['RowData'][$splitColumn] = isset($splitData[$index]) ? $splitData[$index] : '';
                    }
                    unset($row['RowData'][$columnName]);
                }
            }
        }
        return $filteredData;
    }

    public function columnToIndex(string $column): int
    {
        $column = strtoupper($column);
        $index = ord($column) - ord('A');

        return $index;
    }

    public function transformIndexToLetter(int $index): string
    {
        return chr(65 + $index);
    }

    public function transformTextToUppercase(&$data)
    {
        $changed = false;

        foreach ($data as &$row) {
            if (isset($row['RowData'])) {
                foreach ($row['RowData'] as &$cell) {
                    if (is_string($cell)) {
                        $changed = true;
                        $cell = strtoupper($cell);
                    }
                }
            }
        }

        return $changed;
    }

    public function removeAccentsAndApostrophes(&$data)
    {
        $changed = false;
        foreach ($data as $rowIndex => $row) {
            if (isset($row['RowData'])) {
                foreach ($row['RowData'] as $cellIndex => $cell) {
                    $newCell = $this->removeAccents($cell);
                    $newCell = str_replace("'", " ", $newCell);
                    if ($newCell !== $cell) {
                        $data[$rowIndex]['RowData'][$cellIndex] = $newCell;
                        $changed = true;
                    }
                }
            }
        }
        return $changed;
    }

    private function removeAccents($string)
    {
        return strtr(utf8_decode($string), utf8_decode('àáâäãåçèéêëìíîïòóôöõùúûüýÿÀÁÂÄÃÅÇÈÉÊËÌÍÎÏÒÓÔÖÕÙÚÛÜÝ'), 'AAAAAACEEEEIIIIOOOOOUUUUYYAAAAAACEEEEIIIIDNOOOOOUUUUY');
    }

    public function validatePostalCodes(array &$data, int $postalCodeIndex, int $countryIndex): bool
    {
        $changed = false;

        foreach ($data as &$row) {
            if (isset($row['RowData'][$postalCodeIndex]) && isset($row['RowData'][$countryIndex])) {
                $codePostal = trim($row['RowData'][$postalCodeIndex]);
                $pays = strtoupper(trim($row['RowData'][$countryIndex]));

                if (in_array($pays, ['FR', 'FRA', 'FRANCE'])) {
                    if (ctype_digit($codePostal) && strlen($codePostal) < 5) {
                        $row['RowData'][$postalCodeIndex] = str_pad($codePostal, 5, '0', STR_PAD_LEFT);
                        $changed = true;
                    }
                }
            }
        }

        return $changed;
    }

    public function getErrorCells(array &$data): array
    {
        $errorCells = [];

        foreach ($data as $rowIndex => $row) {
            if (isset($row['RowData'])) {
                foreach ($row['RowData'] as $cellIndex => $cell) {
                    if (is_string($cell) && strlen($cell) > 38) {
                        $errorCells[] = [$rowIndex, $cellIndex];
                    }
                }
            }
        }

        return $errorCells;
    }

    public function generateErrorExcel(array $data, array $errorCells): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $errorRows = [];
        foreach ($errorCells as $errorCell) {
            $rowIndex = $errorCell[0];
            if (!isset($errorRows[$rowIndex])) {
                $errorRows[$rowIndex] = $data[$rowIndex];
            }
        }

        $rowIndex = 1;
        foreach ($errorRows as $originalRowIndex => $row) {
            $colIndex = 0;
            foreach ($row['RowData'] as $cell) {
                $cellCoordinate = $this->transformIndexToLetter($colIndex) . $rowIndex;
                $sheet->setCellValue($cellCoordinate, $cell);

                foreach ($errorCells as $errorCell) {
                    if ($errorCell[0] == $originalRowIndex && $errorCell[1] == $colIndex) {
                        $sheet->getStyle($cellCoordinate)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setARGB('FFFF0000');
                    }
                }
                $colIndex++;
            }
            $rowIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'error_excel_') . '.xlsx';
        $writer->save($tempFile);

        return $tempFile;
    }

    public function filterDataBySelectedRows(array $data, array $selectedRows): array
    {
        $filteredData = [];
        foreach ($selectedRows as $rowIndex) {
            if (isset($data[$rowIndex])) {
                $filteredData[] = $data[$rowIndex];
            }
        }
        return $filteredData;
    }

    public function getErrorRowIndices(array $data): array
    {
        $errorRowIndices = [];
        foreach ($data as $rowIndex => $row) {
            if (isset($row['RowData'])) {
                foreach ($row['RowData'] as $cell) {
                    if (is_string($cell) && strlen($cell) > 38) {
                        $errorRowIndices[] = $rowIndex;
                        break;
                    }
                }
            }
        }
        return $errorRowIndices;
    }

    public function validateSelectedColumns(array $data, array $selectedColumns): bool
    {
        $availableColumns = array_keys($data[0]['RowData']);
        foreach ($selectedColumns as $column) {
            if (!in_array($column, $availableColumns)) {
                return false;
            }
        }
        return true;
    }

    public function processDataInBatches(array $data, callable $callback, int $batchSize = 100): array
    {
        $processedData = [];
        $totalRows = count($data);

        for ($i = 0; $i < $totalRows; $i += $batchSize) {
            $batch = array_slice($data, $i, $batchSize);
            $processedData = array_merge($processedData, $callback($batch));
        }

        return $processedData;
    }

    public function applyTransformations(array &$data)
    {
        $this->removeAccentsAndApostrophes($data);
        $this->transformTextToUppercase($data);
    }

    public function mergeCorrectedData(array $mainData, array $correctedData): array
    {
        return array_merge($mainData, $correctedData);
    }

    public function getColumnLettersFromFirstRow(array $filteredData): array
    {
        if (empty($filteredData)) {
            return [];
        }
    
        // Récupérer la première ligne du tableau
        $firstRow = reset($filteredData);
    
        // Compter le nombre de colonnes
        $columnCount = count($firstRow['RowData']);
    
        // Générer les lettres dynamiquement
        $letters = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $letters[] = $this->getExcelColumnName($i);
        }
    
        return $letters;
    }    
        private function getExcelColumnName(int $index): string
    {
        $letters = '';
        while ($index >= 0) {
            $letters = chr(($index % 26) + 65) . $letters;
            $index = floor($index / 26) - 1;
        }
        return $letters;
    }
}
