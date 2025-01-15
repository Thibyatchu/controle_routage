<?php

// src/Controller/HomeController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(Request $request): Response
    {
        // Vérification si un fichier a été envoyé
        if ($request->isMethod('POST') && $request->files->has('file')) {
            // Récupérer le fichier
            $file = $request->files->get('file');
            $filePath = $file->getPathname();
            $fileExtension = strtolower($file->getClientOriginalExtension());

            // Vérifier si l'extension est CSV ou Excel (XLS, XLSX)
            $validExtensions = ['csv', 'xls', 'xlsx'];

            if (!in_array($fileExtension, $validExtensions)) {
                // Si l'extension n'est pas valide, ajouter un message d'erreur à la session
                $this->addFlash('error', 'Veuillez choisir un fichier CSV ou Excel (XLS, XLSX).');
                return $this->redirectToRoute('home');
            }

            // Charger le fichier avec PhpSpreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $data = [];

            // Extraire les données du fichier
            foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                $rowData = [];
                foreach ($row->getCellIterator() as $cell) {
                    $rowData[] = $cell->getValue() ?? '';  // Remplacer les null par des chaînes vides
                }
                $data[] = $rowData;
            }

            // Enregistrer les données dans la session pour les utiliser dans la page d'affichage
            $session = $request->getSession();
            $session->set('spreadsheet_data', $data);

            // Rediriger vers la page d'affichage
            return $this->redirectToRoute('display');
        }

        return $this->render('home/index.html.twig');
    }
}
