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
        // Enregistrer l'option dans la session pour une utilisation future
        $session = $request->getSession();

        // Récupérer les données de la session
        $data = $fileService->getDataFromSession($session);

        // Si aucune donnée n'est trouvée
        if (empty($data)) {
            return $this->render('display/index.html.twig', [
                'data' => [],  // Pas de données à afficher
                'ignore_first_rows' => 'none',
                'colLetters' => [],
                'selected_columns' => [],
            ]);
        }

        // Récupérer la valeur de l'option d'ignorance des lignes
        $ignoreFirstRows = $request->get('ignore_first_rows', 'none');

        // Traiter les données selon l'option d'ignorance des lignes
        $data = $fileService->processDataWithIgnoreOption($data, $ignoreFirstRows);

        // Trouver le nombre de colonnes maximum
        $maxColumns = max(array_map('count', $data));

        // Compléter les lignes manquantes de colonnes avec des chaînes vides
        foreach ($data as &$row) {
            $row = array_pad($row, $maxColumns, '');
        }

        // Générer les lettres des colonnes dynamiquement (A, B, C, ..., Z, AA, AB, ...)
        $colLetters = $fileService->getColumnLetters($maxColumns);

        // Récupérer les colonnes sélectionnées par l'utilisateur
        $selectedColumns = $request->get('selected_columns', []);

        // Enregistrer les colonnes sélectionnées dans la session
        $session->set('selected_columns', $selectedColumns);

        // Filtrer les données en fonction des colonnes sélectionnées
        $filteredData = $fileService->filterDataBySelectedColumns($data, $selectedColumns);

        return $this->render('display/index.html.twig', [
            'data' => $filteredData,  // Afficher les données filtrées
            'ignore_first_rows' => $ignoreFirstRows,
            'colLetters' => $colLetters,  // Passer les lettres des colonnes à la vue
            'selected_columns' => $selectedColumns,  // Passer les colonnes sélectionnées
        ]);
    }
}
