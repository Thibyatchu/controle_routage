<?php

// src/Controller/TreatmentController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileService;

class TreatmentController extends AbstractController
{
    private $fileService;

    // Injection du service FileService
    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    #[Route('/treatment', name: 'app_treatment')]
    public function index(Request $request, FileService $fileService): Response
    {
        // Récupérer les colonnes sélectionnées et les données filtrées
        $selectedColumns = $request->get('selected_columns', '');
        $filteredData = $request->get('filtered_data', []);
    
        // Convertir les colonnes en tableau si nécessaire
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }
    
        // Vérifier que les données sont au bon format
        if (!is_array($selectedColumns) || !is_array($filteredData)) {
            $this->addFlash('error', 'Les données ou colonnes sélectionnées ne sont pas valides.');
            return $this->redirectToRoute('app_display');  // Rediriger vers la page d'affichage
        }
    
        // Appeler le service pour traiter les données
        $filteredDataByColumns = $fileService->filterDataByColumns($filteredData, $selectedColumns);
    
        // Retourner la vue
        return $this->render('treatment/index.html.twig', [
            'selected_columns' => $selectedColumns,
            'filtered_data' => $filteredDataByColumns,
        ]);
    }
}
