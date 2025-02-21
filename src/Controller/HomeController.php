<?php

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
        if ($request->isMethod('POST') && $request->files->has('file')) {
            $file = $request->files->get('file');
            $filePath = $file->getPathname();

            if (!$fileService->validateFileExtension($file)) {
                $this->addFlash('error', 'Veuillez choisir un fichier CSV ou Excel (XLS, XLSX).');
                return $this->redirectToRoute('app_home');
            }

            $maxSizeInBytes = 134217728; // 128 Mo
            $fileSize = $file->getSize();

            if ($fileSize > $maxSizeInBytes) {
                $sizeToReduceInMo = ($fileSize - $maxSizeInBytes) / (1024 * 1024);
                $this->addFlash('error', "Le fichier est trop volumineux. Veuillez réduire sa taille de " . round($sizeToReduceInMo, 2) . " Mo.");
                return $this->redirectToRoute('app_home');
            }

            try {
                $data = $fileService->loadSpreadsheetData($filePath);

                $session = $request->getSession();
                $fileService->storeDataInSession($data, $session);

                return $this->redirectToRoute('app_display');
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                $this->addFlash('error', "Erreur lors du chargement du fichier : " . $e->getMessage());
                return $this->redirectToRoute('app_home');
            }
        }

        return $this->render('home/index.html.twig');
    }
}
