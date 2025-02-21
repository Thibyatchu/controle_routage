<?php

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
        $ignoreFirstRows = $request->get('ignore_first_rows', 'none');
        $session->set('ignore_first_rows', $ignoreFirstRows);

        // 🔹 Récupération des données depuis la session
        $data = $fileService->getDataFromSession($session);
        if (empty($data)) {
            return $this->render('display/index.html.twig', [
                'data' => [],
                'ignore_first_rows' => 'none',
                'colLetters' => [],
                'selected_columns' => [],
            ]);
        }
        // 🔹 Ignorer les premières lignes si demandé
        $data = $fileService->ignoreFirstRows($data, $ignoreFirstRows);
        // 🔹 Limiter l'affichage aux 10 premières lignes pour éviter de surcharger la page
        $data = array_slice($data, 0, 10);

        // 🔹 Récupération des colonnes sélectionnées
        $selectedColumns = $request->get('selected_columns', []);
        $session->set('selected_columns', $selectedColumns);

        // 🔹 Filtrer les données selon les colonnes sélectionnées
        $filteredData = $fileService->filterDataBySelectedColumns($data, $selectedColumns);

        // 🔹 Correction : Récupération dynamique des lettres de colonnes
        $colLetters = $fileService->getColumnLettersFromFirstRow($filteredData);

        // 🔹 Vérification de la taille des données
        $maxSizeInBytes = 134217728; // 128 Mo
        $dataSize = strlen(serialize($data));

        if ($dataSize > $maxSizeInBytes) {
            $sizeToReduceInMo = ($dataSize - $maxSizeInBytes) / (1024 * 1024);
            $this->addFlash('error', "Le fichier est trop volumineux. Veuillez réduire sa taille de " . round($sizeToReduceInMo, 2) . " Mo.");
            return $this->redirectToRoute('app_display');
        }

        // 🔹 Debugging : Vérifier les colonnes générées avant affichage

        return $this->render('display/index.html.twig', [
            'data' => $filteredData,
            'ignore_first_rows' => $ignoreFirstRows,
            'colLetters' => $colLetters,
            'selected_columns' => $selectedColumns,
        ]);
    }
}
