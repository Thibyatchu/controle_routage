<?php

// src/Controller/DisplayController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DisplayController extends AbstractController
{
    #[Route('/display', name: 'app_display')]
    public function index(Request $request, FileService $fileService, SessionInterface $session): Response
    {
        // Récupérer l'option d'ignorance des lignes
        $ignoreFirstRows = $request->get('ignore_first_rows', 'none');

        // Enregistrer cette option dans la session pour la réutiliser dans la page /treatment
        $session->set('ignore_first_rows', $ignoreFirstRows);

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

        // Traiter les données selon l'option d'ignorance des lignes
        $data = $fileService->ignoreFirstRows($data, $ignoreFirstRows);

        // Limiter les données à 10 lignes
        $data = array_slice($data, 0, 10);

        // Récupérer les colonnes sélectionnées par l'utilisateur
        $selectedColumns = $request->get('selected_columns', []);

        // Enregistrer les colonnes sélectionnées dans la session
        $session->set('selected_columns', $selectedColumns);

        // Filtrer les données en fonction des colonnes sélectionnées
        $filteredData = $fileService->filterDataBySelectedColumns($data, $selectedColumns);

        // Calculer les lettres des colonnes (A, B, C, etc.)
        $colLetters = $fileService->getColumnLetters($filteredData);

        // Vérifier la taille des données
        $maxSizeInBytes = 134217728; // 128 Mo
        $dataSize = strlen(serialize($data));

        if ($dataSize > $maxSizeInBytes) {
            // Calculer la taille à réduire
            $sizeToReduce = $dataSize - $maxSizeInBytes;
            $sizeToReduceInMo = $sizeToReduce / (1024 * 1024); // Convertir en Mo

            // Ajouter un message d'erreur à la session
            $this->addFlash('error', "Le fichier est trop volumineux. Veuillez réduire sa taille de " . round($sizeToReduceInMo, 2) . " Mo.");
            return $this->redirectToRoute('app_display');
        }

        return $this->render('display/index.html.twig', [
            'data' => $filteredData,  // Afficher les données filtrées
            'ignore_first_rows' => $ignoreFirstRows,
            'colLetters' => $colLetters,
            'selected_columns' => $selectedColumns,
        ]);
    }
}
