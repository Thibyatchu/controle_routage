<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class DisplayController extends AbstractController
{
    #[Route('/display', name: 'app_display')]
    public function index(Request $request): Response
    {
        // Récupérer les données de la session
        $session = $request->getSession();
        $data = $session->get('spreadsheet_data', []);

        // Récupérer la valeur de l'option d'ignorance des lignes
        $ignoreFirstRows = $request->get('ignore_first_rows', 'none');

        // Si l'utilisateur souhaite ignorer des lignes
        if ($ignoreFirstRows === 'one') {
            // Ignorer la première ligne
            $data = array_slice($data, 1, 10); // Ignore la première ligne, et affiche les 5 suivantes
        } elseif ($ignoreFirstRows === 'two') {
            // Ignorer les deux premières lignes
            $data = array_slice($data, 2, 10); // Ignore les deux premières lignes et affiche les 5 suivantes
        } else {
            // Sinon, afficher les 5 premières lignes
            $data = array_slice($data, 0, 10);
        }

        // Trouver le nombre de colonnes maximum
        $maxColumns = max(array_map('count', $data));

        // Compléter les lignes manquantes de colonnes avec des chaînes vides
        foreach ($data as &$row) {
            $row = array_pad($row, $maxColumns, '');
        }

        // Générer les lettres des colonnes dynamiquement (A, B, C, ..., Z, AA, AB, ...)
        $colLetters = [];
        for ($i = 0; $i < $maxColumns; $i++) {
            if ($i < 26) {
                // A à Z
                $colLetters[] = chr(65 + $i); // De A à Z
            } else {
                // AA, AB, etc.
                $firstLetter = chr(65 + floor($i / 26) - 1); // Lettre de la première partie (A, B, C,...)
                $secondLetter = chr(65 + ($i % 26)); // Lettre de la deuxième partie (A, B, C,...)
                $colLetters[] = $firstLetter . $secondLetter; // Combine les deux parties pour les lettres AA, AB, ...
            }
        }

        return $this->render('display/index.html.twig', [
            'data' => $data,
            'ignore_first_rows' => $ignoreFirstRows,
            'colLetters' => $colLetters,  // On passe les lettres des colonnes à la vue
        ]);
    }
}
