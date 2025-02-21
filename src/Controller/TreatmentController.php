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
        ini_set('memory_limit', '1000M');
    }

    #[Route('/treatment', name: 'app_treatment', methods: ['GET', 'POST'])]
    public function index(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        $ignoreFirstRows = $session->get('ignore_first_rows', 'none');
        $filteredData = $fileService->getDataFromSession($session);

        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée filtrée trouvée dans la session.');
            return $this->redirectToRoute('app_display');
        }

        if ($request->isMethod('POST') && $request->files->has('file')) {
            $correctedFile = $request->files->get('file');

            if (!$fileService->validateFileExtension($correctedFile)) {
                $this->addFlash('error', 'Le fichier importé n\'est pas valide. Veuillez importer un fichier CSV, XLS ou XLSX.');
                return $this->redirectToRoute('app_treatment');
            }

            $correctedFilePath = $correctedFile->getPathname();
            $correctedData = $fileService->loadSpreadsheetData($correctedFilePath);

            if ($correctedData === null) {
                $this->addFlash('error', 'Erreur lors du chargement du fichier corrigé.');
                return $this->redirectToRoute('app_treatment');
            }

            $mainDataWithoutErrors = $session->get('main_data_without_errors', []);
            $mergedData = $fileService->mergeCorrectedData($mainDataWithoutErrors, $correctedData);

            $fileService->storeDataInSession($mergedData, $session);
            $this->addFlash('success', 'Le fichier corrigé a été importé avec succès.');

            return $this->redirectToRoute('app_treatment');
        }

        $changedUppercase = $fileService->transformTextToUppercase($filteredData);
        $changedAccentsApostrophes = $fileService->removeAccentsAndApostrophes($filteredData);

        $filteredData = $fileService->ignoreFirstRows($filteredData, $ignoreFirstRows);

        $codePostal = $request->get('code_postal', '');
        $pays = $request->get('pays', '');
        $changedPostalCodes = false;

        if (!empty($codePostal) && !empty($pays)) {
            $postalCodeIndex = $fileService->columnToIndex($codePostal);
            $countryIndex = $fileService->columnToIndex($pays);
            $changedPostalCodes = $fileService->validatePostalCodes($filteredData, $postalCodeIndex, $countryIndex);
        }

        $raisonSocial = $request->get('raison_social', '');
        $civiliteNomPrenom = $request->get('civilite_nom_prenom', []);
        $adresse1 = $request->get('adresse_1', '');
        $adresse2 = $request->get('adresse_2', '');
        $adresse3 = $request->get('adresse_3', '');
        $codePostal = $request->get('code_postal', '');
        $ville = $request->get('ville', '');
        $pays = $request->get('pays', '');

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

        $selectedColumnsIndexes = [];
        $selectedColumnTitles = [];

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

        if (!$fileService->validateSelectedColumns($filteredData, $selectedColumnsIndexes)) {
            $this->addFlash('error', 'Une ou plusieurs colonnes sélectionnées n\'existent pas dans les données.');
            return $this->redirectToRoute('app_display');
        }

        $dataToDisplay = array_map(function ($row) use ($selectedColumnsIndexes) {
            if (isset($row['RowData'])) {
                return array_intersect_key($row['RowData'], array_flip($selectedColumnsIndexes));
            }
            return [];
        }, $filteredData);

        $dataToDisplay = array_slice($dataToDisplay, 0, 10);

        $errorCells = $fileService->getErrorCells($filteredData);
        $errorRowIndices = $fileService->getErrorRowIndices($filteredData);

        $mainDataWithoutErrors = $fileService->filterDataBySelectedRows($filteredData, $errorRowIndices);
        $session->set('main_data_without_errors', $mainDataWithoutErrors);

        if ($changedUppercase) {
            $this->addFlash('info', 'Toutes les lettres minuscules dans le fichier ont été converties en majuscules.');
        }

        if ($changedAccentsApostrophes) {
            $this->addFlash('info', 'Tous les accents ont été enlevés et les apostrophes ont été supprimées.');
        }

        if ($changedPostalCodes) {
            $this->addFlash('info', 'Les codes postaux ont été vérifiés et corrigés si nécessaire (ajout de zéros devant pour les codes français en cas d\'oubli).');
        }

        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $dataToDisplay,
            'selected_columns' => $selectedColumnsIndexes,
            'selected_column_titles' => $selectedColumnTitles,
            'fileService' => $fileService,
            'changedUppercase' => $changedUppercase,
            'changedAccentsApostrophes' => $changedAccentsApostrophes,
            'changedPostalCodes' => $changedPostalCodes,
            'cellLengthErrorCount' => count($errorCells),
            'errorCells' => $errorCells,
        ]);
    }

    #[Route('/download-error-excel', name: 'app_download_error_excel')]
    public function downloadErrorExcel(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        $filteredData = $fileService->getDataFromSession($session);
        $errorCells = $fileService->getErrorCells($filteredData);
        $errorExcelFile = $fileService->generateErrorExcel($filteredData, $errorCells);

        return new BinaryFileResponse($errorExcelFile);
    }

    #[Route('/download-valid-excel', name: 'app_download_valid_excel', methods: ['GET'])]
    public function downloadValidExcel(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        $filteredData = $fileService->getDataFromSession($session);

        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée trouvée dans la session.');
            return $this->redirectToRoute('app_display');
        }

        $fileService->applyTransformations($filteredData);

        if (empty($filteredData)) {
            $this->addFlash('error', 'Aucune donnée après transformation.');
            return $this->redirectToRoute('app_display');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = $filteredData[0]['RowData'];
        $sheet->fromArray([$headerRow], NULL, 'A1');

        if (count($filteredData) > 1) {
            $sheet->fromArray(array_slice($filteredData, 1), NULL, 'A2');
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'valid_excel_') . '.xlsx';
        $writer->save($tempFile);

        if (!file_exists($tempFile)) {
            $this->addFlash('error', 'Erreur lors de la génération du fichier Excel.');
            return $this->redirectToRoute('app_display');
        }

        return new BinaryFileResponse($tempFile, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="valid_excel.xlsx"',
        ]);
    }

    #[Route('/update-data', name: 'app_update_data', methods: ['POST'])]
    public function updateData(Request $request, SessionInterface $session, FileService $fileService): Response
    {
        $data = $request->request->get('data');
        $filteredData = $fileService->getDataFromSession($session);

        foreach ($data as $rowIndex => $row) {
            if (isset($filteredData[$rowIndex]['RowData'])) {
                foreach ($row as $cellIndex => $value) {
                    $filteredData[$rowIndex]['RowData'][$cellIndex] = $value;
                }
            }
        }

        $fileService->storeDataInSession($filteredData, $session);
        $errorCells = $fileService->getErrorCells($filteredData);

        return $this->render('treatment/index.html.twig', [
            'filtered_data' => $filteredData,
            'selected_columns' => $session->get('selected_columns', []),
            'selected_column_titles' => $session->get('selected_column_titles', []),
            'fileService' => $fileService,
            'changedUppercase' => $session->get('changedUppercase', false),
            'changedAccentsApostrophes' => $session->get('changedAccentsApostrophes', false),
            'changedPostalCodes' => $session->get('changedPostalCodes', false),
            'cellLengthErrorCount' => count($errorCells),
            'errorCells' => $errorCells,
        ]);
    }
}
