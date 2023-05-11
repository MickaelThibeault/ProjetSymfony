<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Form\ProfileUpdateType;
use App\Repository\ParticipantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ParticipantController extends AbstractController{

    #[Route('/profile/update', name: 'profile_update')]

    public function edit(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        // Récupérer l'utilisateur actuel
        $user = $this->getUser();

        // Créer un formulaire pour la modification de profil
        $form = $this->createForm(ProfileUpdateType::class, data: $user);

        // Traiter la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si le pseudo est unique
            $existingParticipant = $entityManager->getRepository(Participant::class)->findOneBy(['pseudo' => $user->getPseudo()]);

            assert($user instanceof Participant);
            $newPassword = $userPasswordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($newPassword);
            $entityManager->persist($user);

            if ($existingParticipant && $existingParticipant->getId() !== $user->getId()) {
                $this->addFlash('erreur', 'Ce pseudo est déjà utilisé.');
                return $this->redirectToRoute('profile_update');
            }

            // Sauvegarder les modifications
            $entityManager->flush();

            // Rediriger l'utilisateur vers la page de modification de profil avec un message de succès
            $this->addFlash('success', 'Votre profil a été mis à jour.');
            return $this->redirectToRoute('sortie_home');
        }
        // Afficher le formulaire de modification de profil
        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    #[Route("/profile/{id}", name:"profile_details")]

    public function details(int $id, ParticipantRepository $participantRepository){
        //fetch participant in database
        $organisateur=$participantRepository->find($id) ;

        return $this->render('/profile/details.html.twig', [
            'organisateur' => $organisateur,
        ]) ;
    }

}
