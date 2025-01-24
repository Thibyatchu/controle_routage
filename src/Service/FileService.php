<?php

// src/Service/FileService.php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FileService
{
    public function validateFileExtension(UploadedFile $file): bool
    {
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $validExtensions = ['csv', 'xls', 'xlsx'];
        
        return in_array($fileExtension, $validExtensions);
    }

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

    public function storeDataInSession(array $data, SessionInterface $session)
    {
        $session->set('spreadsheet_data', $data);
    }

    public function getDataFromSession(SessionInterface $session): array
    {
        return $session->get('spreadsheet_data', []);
    }

    public function processDataWithIgnoreOption(array $data, string $ignoreFirstRows): array
    {
        // Si l'utilisateur souhaite ignorer des lignes
        if ($ignoreFirstRows === 'one') {
            // Ignorer la première ligne
            return array_slice($data, 1, 10); // Ignore la première ligne, et affiche les 10 suivantes
        } elseif ($ignoreFirstRows === 'two') {
            // Ignorer les deux premières lignes
            return array_slice($data, 2, 10); // Ignore les deux premières lignes et affiche les 10 suivantes
        } else {
            // Sinon, afficher les 10 premières lignes
            return array_slice($data, 0, 10);
        }
    }

    public function getColumnLetters(int $maxColumns): array
    {
        $colLetters = [];
        for ($i = 0; $i < $maxColumns; $i++) {
            if ($i < 26) {
                // A à Z
                $colLetters[] = chr(65 + $i); // De A à Z
            } else {
                // AA, AB, etc.
                $firstLetter = chr(65 + floor($i / 26) - 1); // Lettre de la première partie (A, B, C,...)
                $secondLetter = chr(65 + ($i % 26)); // Lettre de la deuxième partie (A, B, C,...)
                $colLetters[] = $firstLetter . $secondLetter; // Combine les deux parties pour les lettres AA, AB, ...
            }
        }

        return $colLetters;
    }

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

    public function filterDataByColumns(array $data, array $selectedColumns): array
    {
        return array_map(function ($row) use ($selectedColumns) {
            return array_filter($row, function ($key) use ($selectedColumns) {
                return in_array($key, $selectedColumns);
            }, ARRAY_FILTER_USE_KEY);
        }, $data);
    }

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

    public function validateAndConvertColumns($selectedColumns): array
    {
        // Convertir les colonnes en tableau si c'est une chaîne de caractères
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }

        return $selectedColumns;
    }

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

    public function prepareCsvData(array $selectedColumns, array $filteredData): array
    {
        $csvData = [];
        
        // Traiter les colonnes sélectionnées et les organiser
        foreach ($selectedColumns as $columnTitle => $columnLetter) {
            if ($columnLetter) {
                if ($columnTitle === 'Civilité, Nom, Prénom') {
                    // Si c'est la liste multiple, on sépare les colonnes pour chaque sélection
                    $letters = explode(',', $columnLetter); // Ex: ['A', 'B', 'C']
                    foreach ($letters as $index => $letter) {
                        // Si c'est la première, on met le titre, sinon on laisse l'en-tête vide
                        $csvData[0][$columnTitle . ($index > 0 ? ' - ' . ($index + 1) : '')] = $letter;
                    }
                } else {
                    // Pour les autres, on récupère la donnée et on l'ajoute au CSV
                    $csvData[0][$columnTitle] = $columnLetter;
                }
            } else {
                // Si la colonne n'a pas été sélectionnée, on ajoute une colonne vide
                $csvData[0][$columnTitle] = '';
            }
        }

        // Ajouter les données dans les bonnes colonnes (par lettre de colonne)
        foreach ($filteredData as $rowIndex => $row) {
            foreach ($selectedColumns as $columnTitle => $columnLetter) {
                // Assure-toi de l'indexation correcte
                $columnIndex = array_search($columnLetter, array_column($filteredData[0], 0)); // Trouver l'indice de la colonne
                if (isset($row[$columnIndex])) {
                    $csvData[$rowIndex + 1][$columnTitle] = $row[$columnIndex];
                } else {
                    $csvData[$rowIndex + 1][$columnTitle] = ''; // Ajouter une cellule vide si aucune donnée n'est présente
                }
            }
        }
        return $csvData;
    }
}
