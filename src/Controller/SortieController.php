<?php

namespace App\Controller;

use App\Entity\Campus;
use App\Entity\Sortie;
use App\Form\SortieType;
use App\Repository\EtatRepository;
use App\Repository\LieuRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SortieRepository;
use App\Repository\VilleRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route('/sortie', name: 'sortie_')]
class SortieController extends AbstractController
{
    #[Route('/home', name: 'home')]
    public function home(Request $request, ParticipantRepository $participantRepository, SortieRepository $sortieRepository): Response
    {
        $user = $this->getUser();
        //$user = $participantRepository->findOneBy(['nom'=>'Letellier']);

        $criteresForm = $this->createFormBuilder()
            ->add('campus', EntityType::class, [
                'label' => 'Campus',
                'class' => Campus::class,
                'choice_label' => 'nom',
                'placeholder' => 'Tous les campus',
                'required' => false
            ])
            ->add('nomSortie', TextType::class, ['label' => 'Le nom de la sortie contient : ', 'required' => false])
            ->add('dateMin', DateTimeType::class, array('widget' => 'single_text', 'label' => 'Entre ', 'required' => false))
            ->add('dateMax', DateTimeType::class, array('widget' => 'single_text', 'label' => 'et ', 'required' => false))
            ->add('organisateur', CheckboxType::class, ['label' => 'Sorties dont je suis l\'organisateur/trice', 'required' => false])
            ->add('inscrit', CheckboxType::class, ['label' => 'Sorties auxquelles je suis inscrit/e', 'required' => false])
            ->add('pasInscrit', CheckboxType::class, ['label' => 'Sorties auxquelles je ne suis pas inscrit/e', 'required' => false])
            ->add('sortiesPassees', CheckboxType::class, ['label' => 'Sorties passées', 'required' => false])
            ->add('recherche', SubmitType::class, ['label' => 'Rechercher'])
            ->getForm();

        $criteresForm->handleRequest($request);

        if ($criteresForm->isSubmitted() && $criteresForm->isValid()) {
            $donnees = $criteresForm->getData();
            $sorties = $sortieRepository->filtrer(
                $donnees['campus'],
                $donnees['nomSortie'],
                $donnees['dateMin'],
                $donnees['dateMax'],
                $donnees['organisateur'],
                $donnees['inscrit'],
                $donnees['pasInscrit'],
                $donnees['sortiesPassees'],
                $user->getId()
            );
        } else {
            $sorties = $sortieRepository->findAll();
        }

        return $this->render('sortie/home.html.twig',
            ['sorties' => $sorties,
                'participant' => $user,
                'criteres' => $criteresForm->createView()
            ]);
    }

    #[Route('/create', name: 'create')]
    #[Security('is_granted(\'ROLE_PARTICIPANT\')')]
    public function create(Request $request, EtatRepository $etatRepository, ParticipantRepository $participantRepository, EntityManagerInterface $entityManager): Response
    {
        $organisateur = $this->getUser();
        //$organisateur = $participantRepository->findOneBy(['nom'=>'Letellier']);

        $sortie = new Sortie();
        $sortie->setOrganisateur($organisateur);
        $sortieCreateForm = $this->createForm(SortieType::class, $sortie);
        $sortieCreateForm->handleRequest($request);

        if ($sortieCreateForm->isSubmitted() && $sortieCreateForm->isValid()) {
            if (in_array("ROLE_PARTICIPANT", $organisateur->getRoles())) {
                $organisateur->setRoles(["ROLE_ORGANISATEUR"]);
                $entityManager->persist($organisateur);
                $entityManager->flush();
            }

            if ($request->request->has('enregistrer')) {
                $sortie->setEtat($etatRepository->findOneBy(['libelle' => 'Créée']));
            } elseif ($request->request->has('publier')) {
                $sortie->setEtat($etatRepository->findOneBy(['libelle' => 'Ouverte']));
            }

            $entityManager->persist($sortie);
            $entityManager->flush();

            if ($request->request->has('enregistrer')) {
                $this->addFlash('success', 'La sortie est créée !!');
            } elseif ($request->request->has('publier')) {
                $this->addFlash('success', 'La sortie est publiée !!');
            }
            return $this->redirectToRoute('sortie_home');
        }
        return $this->render('sortie/create.html.twig', ['sortieForm' => $sortieCreateForm->createView()]);
    }

    #[Route('/update/{id}', name: 'update')]
    #[Security('is_granted(\'ROLE_ORGANISATEUR\')')]
    public function update($id, Request $request, SortieRepository $sortieRepository, LieuRepository $lieuRepository, VilleRepository $villeRepository, EtatRepository $etatRepository, EntityManagerInterface $entityManager): Response
    {

        $sortie = $sortieRepository->find($id);
        if (!$sortie) {
            throw $this->createNotFoundException("Sortie inexistante");
        }
        $sortieUpdateForm = $this->createForm(SortieType::class, $sortie);

        $sortieUpdateForm->handleRequest($request);

        if ($sortieUpdateForm->isSubmitted() && $sortieUpdateForm->isValid()) {

            if ($request->request->has('supprimer')) {
                if (count($sortie->getOrganisateur()->getSorties()) == 1) {
                    $sortie->getOrganisateur()->setRoles(["ROLE_PARTICIPANT"]);
                }
                $entityManager->remove($sortie);
                $entityManager->flush();
                $this->addFlash('success', 'La sortie est supprimée !!');
            } else {
                if ($request->request->has('enregistrer')) {
                    $sortie->setEtat($etatRepository->findOneBy(['libelle' => 'Créée']));
                } elseif ($request->request->has('publier')) {
                    $sortie->setEtat($etatRepository->findOneBy(['libelle' => 'Ouverte']));
                }

                $entityManager->persist($sortie);
                $entityManager->flush();

                if ($request->request->has('enregistrer')) {
                    $this->addFlash('success', 'La modification est effectuée !!');
                } elseif ($request->request->has('publier')) {
                    $this->addFlash('success', 'La sortie est publiée !!');
                }
            }

            return $this->redirectToRoute('sortie_home');
        }

        return $this->render('sortie/update.html.twig', ['sortieForm' => $sortieUpdateForm->createView()]);
    }

    #[Route('/annulation/{id}', name: 'annulation')]
    #[Security('is_granted(\'ROLE_ORGANISATEUR\')')]
    public function annulation($id, Request $request, EtatRepository $etatRepository, SortieRepository $sortieRepository, EntityManagerInterface $entityManager): Response
    {
        $sortie = $sortieRepository->find($id);

        $annulationForm = $this->createFormBuilder()
            ->add('Motif', TextareaType::class,
                ['label' => 'Motif : ',
                    'attr' => ['rows' => 5],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Le motif de l\'annulation est obligatoire'
                        ])
                    ]
                ])
            ->getForm();

        $annulationForm->handleRequest($request);

        if ($annulationForm->isSubmitted() && $annulationForm->isValid()) {
            $motif = $annulationForm->getData();
            $sortie->setEtat($etatRepository->findOneBy(['libelle' => 'Annulée']));
            $sortie->setInfosSortie($motif['Motif']);
            $entityManager->persist($sortie);
            $entityManager->flush();

            $this->addFlash('success', 'L\'annulation est effectuée !!');

            return $this->redirectToRoute('sortie_home');
        }

        return $this->render('sortie/annulation.html.twig', ['annulationForm' => $annulationForm->createView(), 'sortie' => $sortie]);
    }

    #[Route('/show/{id}', name: 'show')]
    #[Security('is_granted(\'ROLE_PARTICIPANT\')')]
    public function show($id, SortieRepository $sortieRepository): Response
    {
        $sortie = $sortieRepository->find($id);

        $participants = $sortie->getParticipants();

        return $this->render('sortie/show.html.twig', ['sortie' => $sortie, 'participants' => $participants]);
    }

    #[Route('/inscription/{idSortie}', name: 'inscription')]
    #[Security('is_granted(\'ROLE_PARTICIPANT\')')]
    //*************Création des enregistrements de la sortie avec ID de la sortie et les ID Participants****************
    public function register(
        int                    $idSortie,
        ParticipantRepository  $participantRepository,
        SortieRepository       $sortieRepository,
        EntityManagerInterface $entityManager,
        Request                $request,
    )
    {
        //Recherche de la personne connectée
        $user = $this->getUser();
        //Recherche de la sortie via son ID
        $sortie = $sortieRepository->find($idSortie);
        //Recherche du participant via son ID
        $participant = $participantRepository->find($user);
        //Est-ce que quelqu'un essaye de t'inscrire à ton insu ?
        if ($user === $participant) {
            if ($sortie->getNbInscriptionsMax() > count($sortie->getParticipants()) && $sortie->getDateLimiteInscription() > new DateTime('NOW')) {
                $sortie->addParticipant($participant);
                //Enregistrement du participant sur la sortie
                $entityManager->persist($sortie);
                $entityManager->flush();

                $this->addFlash('success', 'Inscription réussie !');
                return $this->redirectToRoute('sortie_home');
            } else {
                $this->addFlash('fail', 'L\'inscription a échoué car le nombre de place est déjà rempli ou la date de clôture est dépassée !');
                return $this->redirectToRoute('sortie_home');
            }
        } else {
            $this->addFlash('fail', 'L\'inscription a échoué car vous êtes un usurpateur !');
            return $this->redirectToRoute('sortie_home');
        }
    }

    #[Route('/desinscription/{idSortie}', name: 'desinscription')]
    #[Security('is_granted(\'ROLE_PARTICIPANT\')')]
    //*********************Désinscription d'une sortie avec ID sortie et ID participant ********************************
    public function unsubscribe(
        int                    $idSortie,
        ParticipantRepository  $participantRepository,
        SortieRepository       $sortiesRepository,
        EntityManagerInterface $entityManager,
        Request                $request
    )
    {
        $user = $this->getUser();
        //Recherche de la sortie via son ID
        $sortie = $sortiesRepository->find($idSortie);
        //Recherche du participant via son ID
        $participant = $participantRepository->find($user);
        //Est-ce que quelqu'un essaye de te désinscrire à ton insu ?
        if ($user === $participant) {
            if ($sortie->getDateLimiteInscription() > new DateTime('NOW')) {
                $sortie->removeParticipant($participant);
                //Suppression du participant sur la sortie
                $entityManager->persist($sortie);
                $entityManager->flush();

                $this->addFlash('success', 'Désinscription réussie !');
                return $this->redirectToRoute('sortie_home');
            } else {
                $this->addFlash('fail', 'Vous ne pouvez vous désincrire après la fin des inscriptions !');
                return $this->redirectToRoute('sortie_home');
            }
        } else {
            $this->addFlash('fail', 'La désinscription a échoué car vous êtes un usurpateur !');
            return $this->redirectToRoute('sortie_home');
        }
    }
}
