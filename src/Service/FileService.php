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

    /**
     * Filtrer les données pour ne conserver que les colonnes sélectionnées.
     *
     * @param array $data Les données à filtrer.
     * @param array $selectedColumns Les colonnes sélectionnées (indices).
     * @return array Les données filtrées.
     */
    public function filterDataByColumns(array $data, array $selectedColumns): array
    {
        return array_map(function ($row) use ($selectedColumns) {
            return array_filter($row, function ($key) use ($selectedColumns) {
                return in_array($key, $selectedColumns);
            }, ARRAY_FILTER_USE_KEY);
        }, $data);
    }
}
