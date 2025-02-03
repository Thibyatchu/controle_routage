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

        // Variables pour savoir si un changement a été effectué
        $changedUppercase = false;
        $changedAccentsApostrophes = false;
        $changedPostalCodes = false;

        // Appliquer la transformation des lettres minuscules en majuscules
        $changedUppercase = $fileService->transformTextToUppercase($filteredData);

        // Appliquer la suppression des accents et des apostrophes
        $changedAccentsApostrophes = $fileService->removeAccentsAndApostrophes($filteredData);

        // **APPLIQUER L'IGNORANCE DES LIGNES**
        $filteredData = $fileService->ignoreFirstRows($filteredData, $ignoreFirstRows, $session);

        // Récupérer les colonnes sélectionnées
        $codePostal = $request->get('code_postal', '');
        $pays = $request->get('pays', '');

        // Vérifier et modifier les codes postaux si un pays et un code postal sont sélectionnés
        if (!empty($codePostal) && !empty($pays)) {
            $postalCodeIndex = $fileService->columnToIndex($codePostal);
            $countryIndex = $fileService->columnToIndex($pays);
            $changedPostalCodes = $fileService->validatePostalCodes($filteredData, $postalCodeIndex, $countryIndex);
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

        // Map des titres des colonnes et de leurs noms dans le formulaire
        $filterTitles = [
            'raison_social' => 'Raison Sociale',
            'civilite_nom_prenom' => 'Civilité, Nom, Prénom',
            'adresse_1' => 'Adresse 1',
            'adresse_2' => 'Adresse 2',
            'adresse_3' => 'Adresse 3',
            'code_postal' => 'Code Postal',
            'ville' => 'Ville',
            'pays' => 'Pays'
        ];

        // Construire les indices de colonnes sélectionnées
        $selectedColumnsIndexes = [];
        $selectedColumnTitles = [];

        // Pour chaque filtre sélectionné
        if (!empty($raisonSocial)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($raisonSocial);
            $selectedColumnTitles[] = $filterTitles['raison_social'];
        }

        if (!empty($civiliteNomPrenom)) {
            // Ajout de la première colonne avec son titre
            $selectedColumnsIndexes[] = $fileService->columnToIndex($civiliteNomPrenom[0]);
            $selectedColumnTitles[] = $filterTitles['civilite_nom_prenom'];

            // Ajout des autres colonnes sans titre
            foreach (array_slice($civiliteNomPrenom, 1) as $column) {
                $selectedColumnsIndexes[] = $fileService->columnToIndex($column);
                $selectedColumnTitles[] = ''; // L'en-tête sera vide pour les autres colonnes
            }
        }

        if (!empty($adresse1)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($adresse1);
            $selectedColumnTitles[] = $filterTitles['adresse_1'];
        }

        if (!empty($adresse2)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($adresse2);
            $selectedColumnTitles[] = $filterTitles['adresse_2'];
        }

        if (!empty($adresse3)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($adresse3);
            $selectedColumnTitles[] = $filterTitles['adresse_3'];
        }

        if (!empty($codePostal)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($codePostal);
            $selectedColumnTitles[] = $filterTitles['code_postal'];
        }

        if (!empty($ville)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($ville);
            $selectedColumnTitles[] = $filterTitles['ville'];
        }

        if (!empty($pays)) {
            $selectedColumnsIndexes[] = $fileService->columnToIndex($pays);
            $selectedColumnTitles[] = $filterTitles['pays'];
        }

        // Filtrer les données en utilisant ces indices de colonne
        $dataToDisplay = array_map(function ($row) use ($selectedColumnsIndexes) {
            return array_intersect_key($row, array_flip($selectedColumnsIndexes));
        }, $filteredData);

        // Limiter l'affichage à 10 lignes
        $dataToDisplay = array_slice($dataToDisplay, 0, 10);

        // Vérifier la longueur des cellules
        $cellLengthErrorCount = $fileService->checkCellLength($filteredData);
        $errorCells = $fileService->getErrorCells($filteredData);

        // Passer les données à la vue avec les colonnes sélectionnées et leurs titres
        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $dataToDisplay,
            'selected_columns' => $selectedColumnsIndexes,
            'selected_column_titles' => $selectedColumnTitles, // Passer les titres des colonnes
            'fileService' => $fileService,
            'changedUppercase' => $changedUppercase, // Passer le statut de changement
            'changedAccentsApostrophes' => $changedAccentsApostrophes, // Passer le statut de changement
            'changedPostalCodes' => $changedPostalCodes, // Passer le statut de changement
            'cellLengthErrorCount' => $cellLengthErrorCount, // Passer le nombre de lignes avec des cellules < 38 caractères
            'errorCells' => $errorCells, // Passer les indices des cellules avec des erreurs
        ]);
    }
}