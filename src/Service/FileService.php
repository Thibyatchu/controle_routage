<?php

// src/Service/FileService.php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;

class FileService
{
    /**
    * Valide l'extension du fichier uploadé (CSV, XLS, ou XLSX uniquement).
    */
    public function validateFileExtension(UploadedFile $file): bool
    {
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $validExtensions = ['csv', 'xls', 'xlsx'];
        
        return in_array($fileExtension, $validExtensions);
    }

    /**
    * Charge les données d'un fichier Excel ou CSV dans un tableau PHP.
    */
    public function loadSpreadsheetData($filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $data = [];

        foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $rowData[] = $cell->getValue() ?? '';  // Remplacer les null par des chaînes vides
            }
            $data[] = $rowData;
        }

        return $data;
    }

    /**
    * Stocke les données d'un fichier dans une session.
    */
    public function storeDataInSession(array $data, SessionInterface $session)
    {
        $session->set('spreadsheet_data', $data);
    }

    /**
    * Récupère les données du fichier stockées dans la session.
    */
    public function getDataFromSession(SessionInterface $session): array
    {
        return $session->get('spreadsheet_data', []);
    }
    

    /**
    * Traite les données en ignorant un nombre de lignes définies au début.
    */
    public function processDataWithIgnoreOption(array $data, string $ignoreOption): array
    {
        // Selon l'option, ignorer les premières lignes
        switch ($ignoreOption) {
            case 'one':
                array_shift($data); // Ignorer la première ligne
                break;
            case 'two':
                array_splice($data, 0, 2); // Ignorer les deux premières lignes
                break;
            case 'none':
            default:
                // Ne rien faire
                break;
        }
    
        return $data;
    }
    

    /**
    * Génère une liste des lettres de colonnes (A-Z, AA-ZZ, etc.).
    */
    public function getColumnLetters(array $data): array
    {
        // Supposons que chaque ligne dans les données a le même nombre de colonnes
        // Récupérer les clés des colonnes (indices numériques) et les convertir en lettres
        $numColumns = count($data[0]);
        
        $letters = [];
        for ($i = 0; $i < $numColumns; $i++) {
            $letters[] = chr(65 + $i); // Convertit l'indice en lettre (A, B, C, ...)
        }
    
        return $letters;
    }

    /**
    * Filtre les données selon les colonnes sélectionnées (par lettre de colonne).
    */
    public function filterDataBySelectedColumns(array $data, array $selectedColumns): array
    {
        $filteredData = [];
        if (!empty($selectedColumns)) {
            foreach ($data as $row) {
                $filteredRow = [];
                foreach ($selectedColumns as $col) {
                    $colIndex = ord($col) - 65; // Calculer l'index de la colonne (A=0, B=1,...)
                    $filteredRow[] = $row[$colIndex] ?? ''; // Ajouter la donnée de la colonne
                }
                $filteredData[] = $filteredRow;
            }
        } else {
            // Si aucune colonne n'est sélectionnée, afficher les données d'origine
            $filteredData = $data;
        }

        return $filteredData;
    }

    /**
    * Filtre les données selon les colonnes sélectionnées en utilisant leur clé.
    */
    public function filterDataByColumns(array $data, array $selectedColumns): array
    {
        return array_map(function ($row) use ($selectedColumns) {
            return array_filter($row, function ($key) use ($selectedColumns) {
                return in_array($key, $selectedColumns);
            }, ARRAY_FILTER_USE_KEY);
        }, $data);
    }

    /**
    * Génère un fichier CSV à partir des données filtrées.
    */
    public function generateCsvFromFilteredData(array $filteredData, array $selectedColumns): string
    {
        $handle = fopen('php://temp', 'r+');

        // Ajouter les en-têtes
        fputcsv($handle, $selectedColumns);

        // Ajouter les données filtrées
        foreach ($filteredData as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $csvContent;
    }

    /**
    * Génère un fichier CSV à partir de données brutes.
    */
    public function generateCsvFromData(array $data): string
    {
        $csvContent = '';
        
        // En-têtes du CSV
        $headers = array_keys($data[0]); // Les clés du premier élément sont les en-têtes
        $csvContent .= implode(';', $headers) . "\n";
    
        // Données
        foreach ($data as $row) {
            $csvContent .= implode(';', $row) . "\n";
        }
        
        return $csvContent;
    }    

    /**
    * Prépare les données du CSV selon les colonnes sélectionnées et les filtre.
    */
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
                $result[] = []; // Si aucune colonne sélectionnée, ajouter une colonne vide
            } else {
                // Convertir la lettre de la colonne en un index
                $colIndex = ord(strtoupper($column)) - 65;
                $columnData = [];

                foreach ($data as $row) {
                    $columnData[] = $row[$colIndex] ?? ''; // Si la colonne n'existe pas, laisser vide
                }

                $result[] = $columnData; // Ajouter la colonne aux résultats
            }
        }
        return $result;
    }

    public function splitColumnData(array $filteredData, array $columnsToSplit): array
    {
        foreach ($filteredData as &$row) {
            foreach ($columnsToSplit as $columnName => $splitIndexes) {
                // Si la colonne existe dans la ligne, on la sépare
                if (isset($row[$columnName])) {
                    $splitData = explode(' ', $row[$columnName]); // On suppose ici que les parties sont séparées par un espace
                    foreach ($splitIndexes as $index => $splitColumn) {
                        $row[$splitColumn] = isset($splitData[$index]) ? $splitData[$index] : ''; // Ajouter les parties séparées aux bonnes colonnes
                    }
                    unset($row[$columnName]); // Enlever la colonne d'origine (si elle est séparée)
                }
            }
        }
        return $filteredData;
    }

    /**
     * Convertit une lettre de colonne (A, B, C...) en un indice numérique (0, 1, 2, ...).
     * @param string $column Lettre de la colonne (A, B, C...).
     * @return int L'indice correspondant à la colonne (0, 1, 2...).
     */
    public function columnToIndex(string $column): int
    {
        // Convertir la lettre en majuscule et récupérer son code ASCII
        $column = strtoupper($column);
        $index = ord($column) - ord('A') + 0; // A devient 0, B devient 1, etc.
        
        return $index;
    }

    /**
     * Convertit un indice numérique (0, 1, 2...) en une lettre de colonne (A, B, C...).
     * @param int $index Indice numérique de la colonne (0, 1, 2...).
     * @return string Lettre de la colonne (A, B, C...).
     */
    public function transformIndexToLetter(int $index): string
    {
        return chr(65 + $index); // 0 => A, 1 => B, 2 => C, ...
    }

    public function transformTextToUppercase(array &$data): bool
    {
        $changed = false;
    
        foreach ($data as &$row) {
            foreach ($row as &$cell) {
                if (is_string($cell) && strtolower($cell) !== $cell) {
                    $cell = strtoupper($cell);
                    $changed = true;
                }
            }
        }
    
        return $changed;
    }

    /*
    public function validatePostalCodes(array &$data, string $codePostalColumn): bool
    {
        $changed = false;
    
        foreach ($data as &$row) {
            if (isset($row[$codePostalColumn]) && is_numeric($row[$codePostalColumn])) {
                $codePostal = (string) $row[$codePostalColumn];
    
                // Si le code postal a moins de 5 chiffres, ajouter un zéro devant
                if (strlen($codePostal) < 5) {
                    $row[$codePostalColumn] = str_pad($codePostal, 5, '0', STR_PAD_LEFT);
                    $changed = true;
                }
            }
        }
    
        return $changed;
    }
    */
}
