<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TreatmentController extends AbstractController
{
    public function __construct()
    {
        // Augmenter la limite de mémoire ici
        ini_set('memory_limit', '1000M');
    }

    #[Route('/treatment', name: 'app_treatment', methods: ['GET', 'POST'])]
    public function index(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        // Initialisation par défaut
        $changedUppercase = false;
        $changedAccentsApostrophes = false;
        $changedPostalCodes = false;

        // Récupérer l'option d'ignorance des lignes depuis la session
        $ignoreFirstRows = $session->get('ignore_first_rows', 'none');

        // Récupérer les données filtrées de la session
        $filteredData = $fileService->getDataFromSession($session);

        // Si aucune donnée n'est trouvée, afficher un message d'erreur et rediriger
        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée filtrée trouvée dans la session.');
            return $this->redirectToRoute('app_display');
        }

        // Appliquer les transformations
        $fileService->applyTransformations($filteredData);

        // Variables pour savoir si un changement a été effectué
        $changedUppercase = $fileService->transformTextToUppercase($filteredData);
        $changedAccentsApostrophes = $fileService->removeAccentsAndApostrophes($filteredData);

        // Appliquer l'ignorance des lignes
        $filteredData = $fileService->ignoreFirstRows($filteredData, $ignoreFirstRows);

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
            $selectedColumnsIndexes[] = $fileService->columnToIndex($civiliteNomPrenom[0]);
            $selectedColumnTitles[] = $filterTitles['civilite_nom_prenom'];

            foreach (array_slice($civiliteNomPrenom, 1) as $column) {
                $selectedColumnsIndexes[] = $fileService->columnToIndex($column);
                $selectedColumnTitles[] = '';
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

        // Valider les colonnes sélectionnées
        if (!$fileService->validateSelectedColumns($filteredData, $selectedColumnsIndexes)) {
            $this->addFlash('error', 'Une ou plusieurs colonnes sélectionnées n\'existent pas dans les données.');
            return $this->redirectToRoute('app_display');
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

        // Récupérer les indices des lignes d'erreur
        $errorRowIndices = $fileService->getErrorRowIndices($filteredData);

        // Stocker le fichier principal sans les lignes d'erreur dans la session
        $mainDataWithoutErrors = $fileService->filterDataBySelectedRows($filteredData, $errorRowIndices);
        $session->set('main_data_without_errors', $mainDataWithoutErrors);

        // Réimportation du fichier corrigé
        if ($request->isMethod('POST') && $request->files->has('file')) {
            $correctedFile = $request->files->get('file');

            if (!$fileService->validateFileExtension($correctedFile)) {
                $this->addFlash('error', 'Le fichier importé n\'est pas valide. Veuillez importer un fichier CSV, XLS ou XLSX.');
                return $this->redirectToRoute('app_treatment');
            }

            $correctedFilePath = $correctedFile->getPathname();
            $correctedData = $fileService->loadSpreadsheetData($correctedFilePath);

            // Récupérer le fichier principal sans les lignes d'erreur depuis la session
            $mainDataWithoutErrors = $session->get('main_data_without_errors', []);

            // Fusionner les données
            $mergedData = $this->mergeCorrectedData($mainDataWithoutErrors, $correctedData, $errorRowIndices);

            // Stocker les données fusionnées dans la session
            $fileService->storeDataInSession($mergedData, $session);

            // Récupérer les données fusionnées de la session
            $filteredData = $fileService->getDataFromSession($session);

            // Vérifier la longueur des cellules
            $cellLengthErrorCount = $fileService->checkCellLength($filteredData);
            $errorCells = $fileService->getErrorCells($filteredData);

            // Filtrer les données en utilisant les indices de colonne
            $dataToDisplay = array_map(function ($row) use ($selectedColumnsIndexes) {
                return array_intersect_key($row, array_flip($selectedColumnsIndexes));
            }, $filteredData);

            // Limiter l'affichage à 10 lignes
            $dataToDisplay = array_slice($dataToDisplay, 0, 10);

            $this->addFlash('success', 'Le fichier corrigé a été importé avec succès.');
        }

        // Passer le chemin du fichier temporaire à la vue
        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $dataToDisplay,
            'selected_columns' => $selectedColumnsIndexes,
            'selected_column_titles' => $selectedColumnTitles,
            'fileService' => $fileService,
            'changedUppercase' => $changedUppercase,
            'changedAccentsApostrophes' => $changedAccentsApostrophes,
            'changedPostalCodes' => $changedPostalCodes,
            'cellLengthErrorCount' => $cellLengthErrorCount,
            'errorCells' => $errorCells,
        ]);
    }

    // Méthode pour fusionner les données corrigées avec les données temporaires
    private function mergeCorrectedData(array $mainData, array $correctedData, array $errorRowIndices): array
    {
        foreach ($correctedData as $index => $correctedRow) {
            if (isset($errorRowIndices[$index])) {
                $mainData[$errorRowIndices[$index]] = $correctedRow;
            }
        }
        return $mainData;
    }

    #[Route('/download-error-excel', name: 'app_download_error_excel')]
    public function downloadErrorExcel(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        // Récupérer les données filtrées de la session
        $filteredData = $fileService->getDataFromSession($session);

        // Vérifier la longueur des cellules
        $cellLengthErrorCount = $fileService->checkCellLength($filteredData);
        $errorCells = $fileService->getErrorCells($filteredData);

        // Générer le fichier Excel avec les cellules en erreur colorées en rouge
        $errorExcelFile = $fileService->generateErrorExcel($filteredData, $errorCells);

        // Retourner le fichier Excel en réponse
        return new BinaryFileResponse($errorExcelFile);
    }

    #[Route('/download-valid-excel', name: 'app_download_valid_excel', methods: ['GET'])]
    public function downloadValidExcel(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        // Récupérer les données filtrées de la session
        $filteredData = $fileService->getDataFromSession($session);

        // Vérification des données en session
        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée trouvée dans la session.');
            return $this->redirectToRoute('app_display');
        }

        // Appliquer les transformations
        $fileService->applyTransformations($filteredData);

        // Vérification des transformations
        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée après transformation.');
            return $this->redirectToRoute('app_display');
        }

        // Identifier les colonnes contenant des erreurs
        $errorCells = $fileService->getErrorCells($filteredData);
        $errorColumns = array_unique(array_map(function ($cell) {
            return $cell[1]; // Retourne l'index de la colonne
        }, $errorCells));

        // Identifier les colonnes sans erreurs
        $allColumns = range(0, count($filteredData[0]) - 1); // Toutes les colonnes possibles
        $validColumns = array_diff($allColumns, $errorColumns); // Colonnes sans erreurs

        // Filtrer les données en utilisant les colonnes sans erreurs
        $dataToDisplay = array_map(function ($row) use ($validColumns) {
            return array_intersect_key($row, array_flip($validColumns));
        }, $filteredData);

        // Vérification des données filtrées
        if (empty($dataToDisplay)) {
            $this->addFlash('error', 'Aucune donnée à afficher après filtrage.');
            return $this->redirectToRoute('app_display');
        }

        // Générer un fichier Excel avec les données filtrées
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Ajouter les en-têtes
        $headerRow = array_map(function ($index) use ($filteredData) {
            return $filteredData[0][$index] ?? ''; // Utiliser les en-têtes de la première ligne
        }, $validColumns);
        $sheet->fromArray([$headerRow], NULL, 'A1');

        // Ajouter les données
        $sheet->fromArray($dataToDisplay, NULL, 'A2');

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'valid_excel_') . '.xlsx';
        $writer->save($tempFile);

        // Vérification de la génération du fichier Excel
        if (!file_exists($tempFile)) {
            $this->addFlash('error', 'Erreur lors de la génération du fichier Excel.');
            return $this->redirectToRoute('app_display');
        }

        // Retourner le fichier Excel en réponse
        return new BinaryFileResponse($tempFile, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="valid_excel.xlsx"',
        ]);
    }

    #[Route('/update-data', name: 'app_update_data', methods: ['POST'])]
    public function updateData(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        $data = $request->request->get('data');

        // Mettre à jour les données dans la session
        $filteredData = $fileService->getDataFromSession($session);
        foreach ($data as $rowIndex => $row) {
            foreach ($row as $cellIndex => $value) {
                $filteredData[$rowIndex][$cellIndex] = $value;
            }
        }
        $fileService->storeDataInSession($filteredData, $session);

        // Revalider les données
        $cellLengthErrorCount = $fileService->checkCellLength($filteredData);
        $errorCells = $fileService->getErrorCells($filteredData);

        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $filteredData,
            'selected_columns' => $session->get('selected_columns', []),
            'selected_column_titles' => $session->get('selected_column_titles', []),
            'fileService' => $fileService,
            'changedUppercase' => $session->get('changedUppercase', false),
            'changedAccentsApostrophes' => $session->get('changedAccentsApostrophes', false),
            'changedPostalCodes' => $session->get('changedPostalCodes', false),
            'cellLengthErrorCount' => $cellLengthErrorCount,
            'errorCells' => $errorCells,
        ]);
    }
}
