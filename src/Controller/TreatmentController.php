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
        // Récupérer les données filtrées de la session
        $filteredData = $fileService->getDataFromSession($session);

        // Si aucune donnée n'est trouvée, afficher un message d'erreur et rediriger vers la page d'affichage
        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée filtrée trouvée dans la session.');
            return $this->redirectToRoute('app_display');
        }

        // Récupérer les colonnes sélectionnées par l'utilisateur dans les listes déroulantes
        $raisonSocial = $request->get('raison_social', '');
        $civiliteNomPrenom = $request->get('civilite_nom_prenom', []);
        $adresse1 = $request->get('adresse_1', '');
        $adresse2 = $request->get('adresse_2', '');
        $adresse3 = $request->get('adresse_3', '');
        $codePostal = $request->get('code_postal', '');
        $ville = $request->get('ville', '');
        $pays = $request->get('pays', '');

        // Récupérer l'option d'ignorance des lignes depuis la session
        $ignoreFirstRows = $session->get('ignore_first_rows', 'none'); // Valeur par défaut : aucune ligne ignorée

        // Construire la liste des colonnes sélectionnées et leur correspondance avec les indices
        $selectedColumnsIndexes = [];
        $columns = [];

        if (!empty($raisonSocial)) {
            $selectedColumnsIndexes[] = $raisonSocial;
            $columns['Raison Sociale'] = $raisonSocial;
        }

        if (!empty($civiliteNomPrenom)) {
            foreach ($civiliteNomPrenom as $column) {
                $selectedColumnsIndexes[] = $column;
            }
            $columns['Civilité, Nom, Prénom'] = implode(', ', $civiliteNomPrenom);
        }

        if (!empty($adresse1)) {
            $selectedColumnsIndexes[] = $adresse1;
            $columns['Adresse 1'] = $adresse1;
        }

        if (!empty($adresse2)) {
            $selectedColumnsIndexes[] = $adresse2;
            $columns['Adresse 2'] = $adresse2;
        }

        if (!empty($adresse3)) {
            $selectedColumnsIndexes[] = $adresse3;
            $columns['Adresse 3'] = $adresse3;
        }

        if (!empty($codePostal)) {
            $selectedColumnsIndexes[] = $codePostal;
            $columns['Code Postal'] = $codePostal;
        }

        if (!empty($ville)) {
            $selectedColumnsIndexes[] = $ville;
            $columns['Ville'] = $ville;
        }

        if (!empty($pays)) {
            $selectedColumnsIndexes[] = $pays;
            $columns['Pays'] = $pays;
        }

        // Filtrer les données pour ne conserver que les colonnes sélectionnées
        $dataToDisplay = array_map(function ($row) use ($selectedColumnsIndexes) {
            return array_intersect_key($row, array_flip($selectedColumnsIndexes));
        }, $filteredData);

        // Limiter l'affichage à 10 lignes
        $dataToDisplay = array_slice($dataToDisplay, 0, 10);

        // Retourner la vue pour afficher les données
        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $dataToDisplay,  // Les données filtrées
            'selected_columns' => array_keys($columns),  // Les colonnes sélectionnées (titres)
            'ignore_first_rows' => $ignoreFirstRows, // Option d'ignorance des lignes
        ]);
    }
}
