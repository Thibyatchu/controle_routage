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

            // Vérifier la taille du fichier
            $maxSizeInBytes = 134217728; // 128 Mo
            $fileSize = $file->getSize();

            if ($fileSize > $maxSizeInBytes) {
                // Calculer la taille à réduire
                $sizeToReduce = $fileSize - $maxSizeInBytes;
                $sizeToReduceInMo = $sizeToReduce / (1024 * 1024); // Convertir en Mo

                // Ajouter un message d'erreur à la session
                $this->addFlash('error', "Le fichier est trop volumineux. Veuillez réduire sa taille de " . round($sizeToReduceInMo, 2) . " Mo.");
                return $this->redirectToRoute('app_home');
            }

            // Charger les données du fichier
            try {
                $data = $fileService->loadSpreadsheetData($filePath);
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                // Ajouter un message d'erreur en cas d'échec du chargement
                $this->addFlash('error', "Erreur lors du chargement du fichier : " . $e->getMessage());
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
