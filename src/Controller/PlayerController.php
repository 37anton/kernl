<?php

namespace App\Controller;

use App\Form\PlayerType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PlayerController extends AbstractController
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }


    #[Route('/players', name: 'player_displayPlayers')]
    public function displayPlayers(): Response
    {
        $response = $this->client->request('GET', 'https://hadrien.billard.kernl.fr/api/players');

        $data = $response->toArray();
        $players = $data['data'];

        return $this->render('player/index.html.twig', [
            'players' => $players,
        ]);
    }

    #[Route('/player/{id}', name: 'player_editPlayer')]
    public function editPlayer(Request $request, HttpClientInterface $client, $id): Response
    {
        // Obtenir les données de tous les joueurs
        $response = $client->request('GET', 'https://hadrien.billard.kernl.fr/api/players');
        $players = $response->toArray();

        // Trouver le joueur spécifique par son ID
        $player = array_filter($players['data'], function ($player) use ($id) {
            return $player['id'] == $id;
        });

        // Si le joueur n'est pas trouvé, renvoyer une erreur 404
        if (!$player) {
            throw $this->createNotFoundException('Le joueur demandé n\'existe pas.');
        }

        // Comme array_filter retourne un tableau, nous devons obtenir le premier élément
        $player = reset($player);

        // Convertir le champ 'active' en booléen
        $player['active'] = (bool) $player['active'];

        // Créer et traiter le formulaire
        $form = $this->createForm(PlayerType::class, $player);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $player = $form->getData();

            // Transformer "active" en entier pour la requête
            $player['active'] = (int) $player['active'];

            try {
                // Effectuer une requête PUT pour mettre à jour le joueur
                $response = $this->client->request('POST', 'https://hadrien.billard.kernl.fr/api/players/update/' . $id, [
                    'json' => $player
                ]);

                // Vérifier si la requête a réussi
                if ($response->getStatusCode() !== 200) {
                    $this->addFlash('error', 'Une erreur s’est produite lors de la mise à jour du joueur.');
                    return $this->redirectToRoute('player_edit', ['id' => $id]);
                }
            } catch (Exception $e) {
                $this->addFlash('error', 'Une erreur s’est produite lors de la mise à jour du joueur.');
                return $this->redirectToRoute('player_edit', ['id' => $id]);
            }

            $this->addFlash('success', 'Le joueur a été mis à jour avec succès.');
            return $this->redirectToRoute('player_displayPlayers');
        }


        return $this->render('player/edit.html.twig', [
            'form' => $form->createView(),
            'player' => $player,
        ]);
    }

    #[Route('/players/{id}/delete', name: 'player_deletePlayer', methods: 'DELETE')]
    public function deletePlayer(HttpClientInterface $client, $id): Response
    {
        $response = $client->request('DELETE', 'https://hadrien.billard.kernl.fr/api/players/destroy/' . $id);

        if ($response->getStatusCode() == 200) {
            $this->addFlash('success', 'Player successfully deleted!');
        } else {
            $this->addFlash('error', 'An error occurred while deleting the player.');
        }

        return $this->redirectToRoute('player_displayPlayers');
    }
}
