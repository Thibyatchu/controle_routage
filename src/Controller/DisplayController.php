<?php

// src/Controller/DisplayController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileService;

class DisplayController extends AbstractController
{
    #[Route('/display', name: 'app_display')]
    public function index(Request $request, FileService $fileService): Response
    {
        // Récupérer la session
        $session = $request->getSession();

        // Récupérer les données depuis la session avec getDataFromSession
        $data = $fileService->getDataFromSession($session); // Utilisation de la méthode de FileService

        // Si aucune donnée n'est trouvée dans la session
        if (empty($data)) {
            return $this->render('display/index.html.twig', [
                'data' => [],  // Pas de données à afficher
                'ignore_first_rows' => 'none',
                'colLetters' => [],
                'selected_columns' => [],
            ]);
        }

        // Récupérer l'option d'ignorance des lignes depuis la requête, par défaut "none"
        $ignoreFirstRows = $request->get('ignore_first_rows', 'none');

        // Traiter les données en fonction de l'option d'ignorance des lignes
        $data = $fileService->processDataWithIgnoreOption($data, $ignoreFirstRows);

        // Trouver le nombre de colonnes maximum
        $maxColumns = max(array_map('count', $data));

        // Compléter les lignes manquantes de colonnes avec des chaînes vides
        foreach ($data as &$row) {
            $row = array_pad($row, $maxColumns, '');
        }

        // Générer dynamiquement les lettres des colonnes (A, B, C, ..., Z, AA, AB, ...)
        $colLetters = $fileService->getColumnLetters($maxColumns);

        // Récupérer les colonnes sélectionnées par l'utilisateur depuis la requête
        $selectedColumns = $request->get('selected_columns', []);

        // Si des colonnes sont sélectionnées, filtrer les données en fonction de ces colonnes
        if (!empty($selectedColumns)) {
            $data = $fileService->filterDataBySelectedColumns($data, $selectedColumns);
        }

        // Retourner la vue avec les données traitées et filtrées
        return $this->render('display/index.html.twig', [
            'data' => $data,  // Afficher les données traitées
            'ignore_first_rows' => $ignoreFirstRows,
            'colLetters' => $colLetters,  // Passer les lettres des colonnes à la vue
            'selected_columns' => $selectedColumns,  // Passer les colonnes sélectionnées
        ]);
    }
}

