<?php namespace Slackwolf\Game\Command;

use Exception;
use InvalidArgumentException;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slackwolf\Game\Formatter\ChannelIdFormatter;
use Slackwolf\Game\Formatter\KillFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\Game;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Role;
use Slackwolf\Game\OptionManager;
use Slackwolf\Game\OptionName;

class KillCommand extends Command
{
    /**
     * @var Game
     */
    private $game;

    public function init()
    {
        $client = $this->client;

        if ($this->channel[0] != 'D') {
            throw new Exception("Vous devez utiliser la commande !kill par message privé au bot.");
        }

        if (count($this->args) < 2) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Commande invalide. Utilisation: !kill #channel @joueur", $channel);
                   });
            throw new InvalidArgumentException("Pas assez d'arguments.");
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
                                 $this->client->send(":warning: Channel spécifié invalide. Utilisation: !kill #channel @joueur", $dmc);
                             }
                         );
            throw new InvalidArgumentException();
        }

        $this->game = $this->gameManager->getGame($channelId);

        if ( ! $this->game) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Aucun jeu en cours.", $channel);
                   });
            throw new Exception("Aucun jeu en cours.");
        }
        
        $this->args[1] = UserIdFormatter::format($this->args[1], $this->game->getOriginalPlayers());
    }

    public function fire()
    {
        $client = $this->client;
        if ($this->game->getWolvesVoted()){
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Les Loups-Garous ont déjà voté.", $channel);
                   });
            throw new Exception("Les Loups-Garous ne peuvent pas voter après la fin du vote.");
        }

        if ($this->game->getState() != GameState::NIGHT) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous pouvez tuer uniquement pendant la nuit.", $channel);
                   });
            throw new Exception("Impossible de tuer en dehors de la nuit.");
        }

        // Voter should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous n'êtes pas vivant dans le channel spécifié.", $channel);
                   });
            throw new Exception("Impossible de tuer en étant mort.");
        }

        // Person player is voting for should also be alive
        if ( ! $this->game->isPlayerAlive($this->args[1])) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Impossible de trouver ce joueur.", $channel);
                   });
            throw new Exception("Joueur voté pas dans la partie.");
        }

        // Person should be werewolf
        $player = $this->game->getPlayerById($this->userId);

        if ($player->role != Role::WEREWOLF) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Vous devez être un Loup-Garou pour tuer.", $channel);
                   });
            throw new Exception("Seulement les Loups-Garous peuvent tuer");
        }

        if ($this->game->hasPlayerVoted($this->userId)) {               
            //If changeVote is not enabled and player has already voted, do not allow another vote
            if (!$this->gameManager->optionsManager->getOptionValue(OptionName::changevote))
            {
                throw new Exception("Changement de vote non autorisé.");
            }
        
            $this->game->clearPlayerVote($this->userId);
        }

        $this->game->vote($this->userId, $this->args[1]);

        $msg = KillFormatter::format($this->game);

        foreach($this->game->getPlayersOfRole(Role::WEREWOLF) as $player) {
            $client->getDMByUserID($player->getId())
                ->then(function(DirectMessageChannel $channel) use ($client,$msg) {
                    $client->send($msg,$channel);
                });
        }

        foreach ($this->game->getPlayersOfRole(Role::WEREWOLF) as $player)
        {
            if ( ! $this->game->hasPlayerVoted($player->getId())) {
                return;
            }
        }

        $votes = $this->game->getVotes();

        if (count($votes) > 1) {
            $this->game->clearVotes();
            foreach($this->game->getPlayersOfRole(Role::WEREWOLF) as $player) {
                $client->getDMByUserID($player->getId())
                       ->then(function(DirectMessageChannel $channel) use ($client) {
                           $client->send(":warning: Les Loups-Garous n'ont pas voté à l'unanimité sur un Villageois. Merci de voter à nouveau.",$channel);
                       });
            }
            return;
        }

        $this->game->setWolvesVoted(true);

        $this->gameManager->changeGameState($this->game->getId(), GameState::DAY);
    }
}
