<?php

// src/Controller/HomeController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileService;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(Request $request, FileService $fileService): Response
    {
        // Vérification si un fichier a été envoyé
        if ($request->isMethod('POST') && $request->files->has('file')) {
            // Récupérer le fichier
            $file = $request->files->get('file');
            $filePath = $file->getPathname();

            // Vérifier si l'extension est CSV ou Excel (XLS, XLSX)
            if (!$fileService->validateFileExtension($file)) {
                // Si l'extension n'est pas valide, ajouter un message d'erreur à la session
                $this->addFlash('error', 'Veuillez choisir un fichier CSV ou Excel (XLS, XLSX).');
                return $this->redirectToRoute('app_home');
            }

            // Charger les données du fichier
            $data = $fileService->loadSpreadsheetData($filePath);

            // Vérifier le nombre de lignes
            $lineCount = count($data);
            $maxLines = 1510;

            if ($lineCount > $maxLines) {
                // Calculer le nombre de lignes à retirer
                $linesToRemove = $lineCount - $maxLines;
                // Ajouter un message d'erreur à la session
                $this->addFlash('error', "Le fichier contient trop de lignes. Veuillez réduire le fichier de $linesToRemove lignes.");
                return $this->redirectToRoute('app_home');
            }

            // Enregistrer les données dans la session pour les utiliser dans la page d'affichage
            $session = $request->getSession();
            $fileService->storeDataInSession($data, $session);

            // Rediriger vers la page d'affichage
            return $this->redirectToRoute('app_display');
        }

        return $this->render('home/index.html.twig');
    }
}


