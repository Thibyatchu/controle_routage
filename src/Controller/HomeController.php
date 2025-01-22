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
    private $fileService;

    // Injection du service FileService
    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    #[Route('/home', name: 'app_home')]
    public function index(Request $request): Response
    {
        // Vérification si un fichier a été envoyé
        if ($request->isMethod('POST') && $request->files->has('file')) {
            // Récupérer le fichier
            $file = $request->files->get('file');
            $filePath = $file->getPathname();

            // Vérifier si l'extension est CSV ou Excel (XLS, XLSX)
            if (!$this->fileService->validateFileExtension($file)) {
                // Si l'extension n'est pas valide, ajouter un message d'erreur à la session
                $this->addFlash('error', 'Veuillez choisir un fichier CSV ou Excel (XLS, XLSX).');
                return $this->redirectToRoute('app_home');
            }

            // Charger les données du fichier
            $data = $this->fileService->loadSpreadsheetData($filePath);

            // Enregistrer les données dans la session pour les utiliser dans la page d'affichage
            $session = $request->getSession();
            $this->fileService->storeDataInSession($data, $session);

            // Rediriger vers la page d'affichage
            return $this->redirectToRoute('app_display');
        }

        return $this->render('home/index.html.twig');
    }
}
