<?php

// src/Controller/TreatmentController.php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\FileService;

class TreatmentController extends AbstractController
{
    #[Route('/treatment', name: 'app_treatment')]
    public function index(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        echo $request->get('raison_social');
        echo $request->get('adresse_1');
        die();

        // Récupérer les données filtrées de la session
        $filteredData = $fileService->getDataFromSession($session);

        // Si aucune donnée n'est trouvée, afficher un message d'erreur et rediriger vers la page de traitement
        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée filtrée trouvée dans la session.');
            return $this->redirectToRoute('app_treatment');
        }

        // Vérifier si le bouton "Télécharger" a été cliqué
        if ($request->isMethod('POST')) {
            $selectedColumns = $request->get('selected_columns', []);

            // Vérification des colonnes sélectionnées
            if (empty($selectedColumns)) {
                $this->addFlash('error', 'Aucune colonne sélectionnée pour le téléchargement.');
                return $this->redirectToRoute('app_treatment');
            }

            // Générer le fichier CSV à partir des données filtrées
            $csvContent = $fileService->generateCsvFromFilteredData($filteredData, $selectedColumns);

            // Retourner la réponse de téléchargement
            return new Response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="filtered_data.csv"',
            ]);
        }

        // Récupérer les colonnes sélectionnées pour affichage
        $selectedColumns = $request->get('selected_columns', []);

        // Afficher les données sur la page de traitement
        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $filteredData,
            'selected_columns' => $selectedColumns,
        ]);
    }
}
