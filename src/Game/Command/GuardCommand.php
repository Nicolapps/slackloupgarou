<?php namespace Slackwolf\Game\Command;

use Exception;
use InvalidArgumentException;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slackwolf\Game\Formatter\ChannelIdFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\Game;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Role;

class GuardCommand extends Command
{
    /**
     * @var Game
     */
    private $game;

    public function init()
    {
        $client = $this->client;

        if ($this->channel[0] != 'D') {
            throw new Exception("Vous pouvez uniquement utiliser la commande !guard par message privé.");
        }

        if (count($this->args) < 2) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Commande invalide. Utilisation: !guard #channel @joueur", $channel);
                   });
            throw new InvalidArgumentException("Pas assez d'arguments");
        }

        $client = $this->client;

        $channelId   = null;
        $channelName = "";

        if (strpos($this->args[0], '#C') !== false) {
            $channelId = ChannelIdFormatter::format($this->args[0]);
        } else {
            if (strpos($this->args[0], '#') !== false) {
                $channelName = substr($this->args[0], 1);
            } else {
                $channelName = $this->args[0];
            }
        }

        if ($channelId != null) {
            $this->client->getChannelById($channelId)
                         ->then(
                             function (ChannelInterface $channel) use (&$channelId) {
                                 $channelId = $channel->getId();
                             },
                             function (Exception $e) {
                                 // Do nothing
                             }
                         );
        }

        if ($channelId == null) {
            $this->client->getGroupByName($channelName)
                         ->then(
                             function (ChannelInterface $channel) use (&$channelId) {
                                 $channelId = $channel->getId();
                             },
                             function (Exception $e) {
                                 // Do nothing
                             }
                         );
        }

        if ($channelId == null) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Commande invalide. Utilisation: !guard #channel @joueur", $dmc);
                             }
                         );
            throw new InvalidArgumentException();
        }

        $this->game = $this->gameManager->getGame($channelId);

        if ( ! $this->game) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Aucun jeu en cours", $channel);
                   });
            throw new Exception("Aucun jeu en cours");
        }
        
        $this->args[1] = UserIdFormatter::format($this->args[1], $this->game->getOriginalPlayers());
    }

    public function fire()
    {
        $client = $this->client;

        if ($this->game->getState() != GameState::NIGHT) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous pouvez uniquement protéger la nuit", $channel);
                   });
            throw new Exception("Impossible de protéger en dehors de la nuit");
        }

        // Voter should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous n'êtes pas vivant dans la partie spécifiée.", $channel);
                   });
            throw new Exception("Impossible de protéger en étant mort.");
        }

        // Person player is voting for should also be alive
        if ( ! $this->game->isPlayerAlive($this->args[1])) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Ce joueur n'a pas pu être trouvé", $channel);
                   });
            throw new Exception("Le joueur demandé n'a pas été trouvé dans la partie.");
        }

        // Person should be werewolf
        $player = $this->game->getPlayerById($this->userId);

        if ($player->role != Role::BODYGUARD) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous devez être Salvateur pour pouvoir protéger", $channel);
                   });
            throw new Exception("Uniquement le Salvateur peut protéger.");
        }

        if ($this->game->getGuardedUserId() !== null) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous avez déjà protégé.", $channel);
                   });
            throw new Exception("Vous avez déjà protégé.");
        }

        if ($this->game->getLastGuardedUserId() == $this->args[1]) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous ne pouvez pas protéger le même joueur que la nuit dernière.", $channel);
                   });
            throw new Exception(":warning: Vous ne pouvez pas protéger le même joueur que la nuit dernière.");
        }

        $this->game->setGuardedUserId($this->args[1]);

        $client->getChannelGroupOrDMByID($this->channel)
               ->then(function (ChannelInterface $channel) use ($client) {
                   $client->send("Joueur protégé avec succès", $channel);
               });

        $this->gameManager->changeGameState($this->game->getId(), GameState::DAY);
    }
}
