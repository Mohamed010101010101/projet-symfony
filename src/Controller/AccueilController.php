<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Commentaire;
use App\Entity\Publication;
use App\Entity\User;
use App\Form\ChatType;
use App\Form\CommentaireType;
use App\Form\ProfileEditType;
use App\Form\ProfilePictureType;
use App\Form\PublicationType;
use App\Form\RegistrationFormType;
use App\Form\UserProfileType;
use App\Repository\ChatRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AccueilController extends AbstractController
{
    #[Route('/accueil', name: 'user_accueil')]
    public function accueil(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Récupère l'utilisateur connecté
        $user = $this->getUser();

        // Récupérer les amis de l'utilisateur connecté (vous devez adapter cette logique)
        $friends = $entityManager->getRepository(User::class)->findFriendsForUser($user);


        // Récupère toutes les publications triées par date (les plus récentes en premier)
        $publications = $entityManager->getRepository(Publication::class)
            ->findBy([], ['created_at' => 'DESC']);

        // Formulaire pour ajouter une nouvelle publication
        $newPublication = new Publication();
        $publicationForm = $this->createFormBuilder($newPublication)
            ->add('content')
            ->getForm();

        $publicationForm->handleRequest($request);

        if ($publicationForm->isSubmitted() && $publicationForm->isValid()) {
            // Définir l'utilisateur connecté et la date
            $newPublication->setUser($user);
            $newPublication->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($newPublication);
            $entityManager->flush();

            return $this->redirectToRoute('user_accueil');
        }

        // Générer les formulaires de commentaires pour chaque publication
        $forms = [];
        foreach ($publications as $publication) {
            $commentaire = new Commentaire();
            $commentaire->setPublication($publication);
            $commentaire->setUser($user);

            $form = $this->createForm(CommentaireType::class, $commentaire);
            $forms[$publication->getId()] = $form->createView();
        }

        return $this->render('accueil/accueil.html.twig', [
            'publications' => $publications,
            'forms' => $forms,
            'publicationForm' => $publicationForm->createView(),
            'friends' => $friends, // Passer les amis à la vue
        ]);
    }




    // Dans AccueilController

    /*#[Route('/publication/ajouter', name: 'publication_ajouter')]
    public function ajouterPublication(Request $request, EntityManagerInterface $entityManager): Response
    {
        $publication = new Publication();
        $form = $this->createForm(PublicationType::class, $publication);

        // Assigner l'utilisateur connecté à la publication
        $publication->setUser($this->getUser());
        $publication->setCreatedAt(new \DateTimeImmutable()); // Fixe la date de création de la publication

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarde la publication dans la base de données
            $entityManager->persist($publication);
            $entityManager->flush();

            // Redirection après la publication
            return $this->redirectToRoute('user_accueil');
        }

        return $this->render('accueil/ajouter_publication.html.twig', [
            'form' => $form->createView(),
        ]);
    }*/


    // Ajouter un commentaire à une publication
    #[Route('/commenter/{id}', name: 'commenter')]
    public function commenter(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        // Récupérer la publication correspondante à l'ID
        $publication = $entityManager->getRepository(Publication::class)->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        // Créer une nouvelle instance de Commentaire
        $commentaire = new Commentaire();
        $commentaire->setPublication($publication);
        $commentaire->setUser($this->getUser());
        $commentaire->setCreatedAt(new \DateTimeImmutable());

        // Créer et gérer le formulaire
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarder le commentaire dans la base de données
            $entityManager->persist($commentaire);
            $entityManager->flush();

            // Rediriger après avoir ajouté le commentaire
            return $this->redirectToRoute('user_accueil');
        }

        // Passer le formulaire et la publication à la vue
        return $this->render('accueil/commenter.html.twig', [
            'form' => $form->createView(),
            'publication' => $publication,
        ]);
    }

    #[Route('/messages/{friend}', name: 'message_chat')]
    public function messages_chat(
        Request $request,
        EntityManagerInterface $entityManager,
        ChatRepository $chatRepository,
        UserRepository $userRepository,
        $friend // Paramètre 'friend' pour récupérer l'ami spécifique
    ): Response {
        $user = $this->getUser();
        $message = new Chat();
        $form = $this->createForm(ChatType::class, $message);

        $form->handleRequest($request);

        // Récupération de l'ami à partir de l'ID
        $recipient = $userRepository->find($friend); // 'friend' est l'ID de l'ami passé dans l'URL

        // Vérifier si l'ami existe
        if (!$recipient) {
            $this->addFlash('error', 'Destinataire introuvable.');
            return $this->redirectToRoute('messages');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $message->setSender($user);
            $message->setRecipient($recipient);
            $message->setCreatedAt(new \DateTimeImmutable()); // Assurez-vous d'ajouter une date de création au message

            // Enregistrer le message
            $entityManager->persist($message);
            $entityManager->flush();

            $this->addFlash('success', 'Message envoyé avec succès!');
            return $this->redirectToRoute('message_chat', ['friend' => $friend]); // Redirige vers la même page pour afficher le message envoyé
        }

        // Récupérer les messages entre l'utilisateur et son ami
        $messages = $chatRepository->findMessagesBetweenUsers($user, $recipient);

        return $this->render('accueil/messages.html.twig', [
            'form' => $form->createView(),
            'messages' => $messages, // Passer les messages à la vue
            'friend' => $recipient, // Passer l'ami à la vue
        ]);
    }



    #[Route('/messages', name: 'messages')]
    public function messages(EntityManagerInterface $entityManager): Response
    {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Récupérer les amis ou contacts associés à l'utilisateur connecté
        $friends = $entityManager->getRepository(User::class)->findFriendsForUser($user);

        return $this->render('accueil/messages.html.twig', [
            'friends' => $friends,
        ]);
    }





    #[Route('/message/{friend}', name: 'message_chat')]
    public function messageChat(string $friend): Response
    {
        // Exemple de messages pour chaque ami (en pratique, vous pouvez les récupérer de la base de données)
        $messages = [
            'kenza' => [
                ['sender' => 'Kenza', 'message' => 'hello riche cv'],
                ['sender' => 'Vous', 'message' => 'ahla kenza'],
                ['sender' => 'Vous', 'message' => 'wlh hmdlh hani nekhdem fi projet'],
                ['sender' => 'Kenza', 'message' => 'rabyy maak nchlh']
            ],
            'zikou' => [
                ['sender' => 'Zikou', 'message' => 'Salut ! Comment ça va ?'],
                ['sender' => 'Vous', 'message' => 'Ça va bien, et toi ?'],
                ['sender' => 'Zikou', 'message' => 'Super, je bosse sur un nouveau projet.'],
                ['sender' => 'Vous', 'message' => 'Pareil ici, j’espère que ça avance bien.']
            ],
            'ibtihel' => [
                ['sender' => 'Ibtihel', 'message' => 'Tu as vu le dernier film ?'],
                ['sender' => 'Vous', 'message' => 'Non, pas encore. Il est bien ?'],
                ['sender' => 'Ibtihel', 'message' => 'Oui, vraiment génial. Tu devrais le voir.'],
                ['sender' => 'Vous', 'message' => 'Je vais essayer de le regarder ce week-end.']
            ],
            'aziz' => [
                ['sender' => 'Aziz', 'message' => 'On se capte ce week-end pour sortir ?'],
                ['sender' => 'Vous', 'message' => 'Avec plaisir ! Où veut-on aller ?'],
                ['sender' => 'Aziz', 'message' => 'Je pensais à la plage.'],
                ['sender' => 'Vous', 'message' => 'C’est une super idée ! À quelle heure ?']
            ],
        ];

        // Récupérer les messages pour l'ami actuel
        $messagesForFriend = $messages[strtolower($friend)] ?? [];

        return $this->render('accueil/chat.html.twig', [
            'friend' => ucfirst($friend),
            'messages' => $messagesForFriend
        ]);
    }





    #[Route('/deconnecter', name: 'user_deconnecter')]
    public function deconnecter(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(registrationFormType::class);
        $form->handleRequest($request);
        {


            return $this->render('registration/register.html.twig', [
                'registrationForm' => $form->createView(),
            ]);
        }
    }



    // Route pour afficher le profil
    #[Route('/profil', name: 'user_profil')]
    public function profil(): Response
    {
        $user = $this->getUser(); // Récupère l'utilisateur connecté

        return $this->render('accueil/profil.html.twig', [
            'user' => $user, // Passe l'utilisateur au template, mais pas de formulaire
        ]);
    }

    // Route pour éditer le profil
    #[Route('/profil/modifier', name: 'edit_profile')]
    public function editProfile(Request $request, ParameterBagInterface $params)
    {
        $user = $this->getUser(); // Récupérer l'utilisateur connecté

        // Créer un formulaire pour la photo de profil
        $form = $this->createForm(ProfilePictureType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profilePicture = $form->get('profilePicture')->getData();

            if ($profilePicture instanceof UploadedFile) {
                // Générer un nouveau nom unique pour le fichier
                $newFilename = uniqid() . '.' . $profilePicture->guessExtension();

                // Déplacer le fichier dans le répertoire des photos de profil
                $profilePicture->move(
                    $params->get('profile_pictures_directory'),
                    $newFilename
                );

                // Mettre à jour l'utilisateur avec le nom de fichier
                $user->setProfilePicture($newFilename);

                // Sauvegarder l'utilisateur
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->flush();

                $this->addFlash('success', 'Votre photo de profil a été mise à jour !');

                return $this->redirectToRoute('user_profil');
            }
        }

        // Passer le formulaire à la vue
        return $this->render('accueil/profil.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/edit', name: 'profile_edit')]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser(); // Récupérer l'utilisateur connecté

        $form = $this->createForm(ProfileEditType::class, $user); // Créer le formulaire de modification du profil
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); // Enregistrer les modifications dans la base de données
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('user_profil'); // Rediriger vers la page de profil après l'édition
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }



    private function getDoctrine()
    {
    }
}
