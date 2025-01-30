<?php

// src/Controller/TreatmentController.php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileService;

class TreatmentController extends AbstractController
{
    #[Route('/treatment', name: 'app_treatment')]
    public function index(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        // Récupérer l'option d'ignorance des lignes depuis la session
        $ignoreFirstRows = $session->get('ignore_first_rows', 'none');

        // Récupérer les données filtrées de la session
        $filteredData = $fileService->getDataFromSession($session);

        // Si aucune donnée n'est trouvée, afficher un message d'erreur et rediriger
        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée filtrée trouvée dans la session.');
            return $this->redirectToRoute('app_display');
        }

        // Traiter les données selon l'option d'ignorance des lignes
        $filteredData = $fileService->processDataWithIgnoreOption($filteredData, $ignoreFirstRows);

        // Récupérer les colonnes sélectionnées par l'utilisateur dans les listes déroulantes
        $raisonSocial = $request->get('raison_social', '');
        $civiliteNomPrenom = $request->get('civilite_nom_prenom', []);
        $adresse1 = $request->get('adresse_1', '');
        $adresse2 = $request->get('adresse_2', '');
        $adresse3 = $request->get('adresse_3', '');
        $codePostal = $request->get('code_postal', '');
        $ville = $request->get('ville', '');
        $pays = $request->get('pays', '');

        // Convertir les lettres des colonnes en indices
        $selectedColumnsIndexes = [];

        if (!empty($raisonSocial)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($raisonSocial);
        }

        if (!empty($civiliteNomPrenom)) {
            foreach ($civiliteNomPrenom as $column) {
                $selectedColumnsIndexes[] = $fileService->columnToIndex($column);
            }
        }

        if (!empty($adresse1)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($adresse1);
        }

        if (!empty($adresse2)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($adresse2);
        }

        if (!empty($adresse3)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($adresse3);
        }

        if (!empty($codePostal)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($codePostal);
        }

        if (!empty($ville)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($ville);
        }

        if (!empty($pays)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($pays);
        }

        // Filtrer les données en utilisant ces indices de colonne
        $dataToDisplay = array_map(function ($row) use ($selectedColumnsIndexes) {
            return array_intersect_key($row, array_flip($selectedColumnsIndexes));
        }, $filteredData);

        // Limiter l'affichage à 10 lignes
        $dataToDisplay = array_slice($dataToDisplay, 0, 10);

        // Passer les données à la vue avec les colonnes sélectionnées
        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $dataToDisplay,
            'selected_columns' => $selectedColumnsIndexes,
            'fileService' => $fileService,
        ]);
    }
}