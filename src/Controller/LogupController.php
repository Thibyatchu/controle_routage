<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LogupController extends AbstractController
{
    #[Route('/logup', name: 'app_logup')]
    public function logup(Request $request, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification si l'email est déjà utilisé
            if ($userRepository->findOneByEmail($user->getEmail()) !== null) {
                return $this->render('logup/index.html.twig', [
                    'user' => $user,
                    'form' => $form->createView(),
                    'message' => "Cet email est déjà utilisé, merci d'en changer",
                ]);
            }

            // Hachage du mot de passe avec UserPasswordHasherInterface
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $user->getPassword()
            );
            $user->setPassword($hashedPassword);

            // Définir le rôle de l'utilisateur
            $user->setRoles(['ROLE_USER']);

            // Persist et flush dans la base de données
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            // Rediriger après une inscription réussie
            return $this->redirectToRoute('app_logup', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('logup/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
