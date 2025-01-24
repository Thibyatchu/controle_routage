<?php

// src/Controller/TreatmentController.php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\FileService;

class TreatmentController extends AbstractController
{
    private $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    #[Route('/treatment', name: 'app_treatment')]
    public function index(Request $request): Response
    {
        $selectedColumns = $request->get('selected_columns', []);
        $filteredData = $request->get('filtered_data', []);

        // Vérification de la validité des données et des colonnes sélectionnées
        if (empty($selectedColumns) || empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée ou colonne sélectionnée.');
            return $this->redirectToRoute('app_display');
        }

        // Préparer les données pour le CSV
        $csvData = $this->fileService->prepareCsvData($selectedColumns, $filteredData);

        // Générer le fichier CSV
        $csvContent = $this->fileService->generateCsvFromData($csvData);

        // Créer la réponse de téléchargement
        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="filtered_data.csv"',
        ]);
    }
}
