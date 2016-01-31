<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slackwolf\Game\Formatter\ChannelIdFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\Game;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Role;
use Zend\Loader\Exception\InvalidArgumentException;

class SeeCommand extends Command
{
    /**
     * @var Game
     */
    private $game;

    /**
     * @var string
     */
    private $gameId;

    /**
     * @var string
     */
    private $chosenUserId;

    public function init()
    {
        $client = $this->client;

        if ($this->channel[0] != 'D') {
            throw new Exception("Vous pouvez uniquement !see par message privé.");
        }

        if (count($this->args) < 2) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Pas assez d'arguments. Utilisation : !see #channel @joueur", $dmc);
                             }
                         );

            throw new InvalidArgumentException();
        }

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
                                 $this->client->send(":warning: Channel invalide. Utilisation : !see #channel @joueur", $dmc);
                             }
                         );
            throw new InvalidArgumentException();
        }

        $this->game   = $this->gameManager->getGame($channelId);
        $this->gameId = $channelId;

        if (!$this->game) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Impossible de trouver une partie en cours sur le channel spécifié", $dmc);
                             }
                         );

            throw new InvalidArgumentException();
        }

        $this->args[1] = UserIdFormatter::format($this->args[1], $this->game->getOriginalPlayers());
        $this->chosenUserId = $this->args[1];

        $player = $this->game->getPlayerById($this->userId);

        if ( ! $player) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: Vous n'êtes pas dans la partie spécifiée.", $dmc);
                     }
                 );

            throw new InvalidArgumentException();
        }

        // Player should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            $client->getChannelGroupOrDMByID($this->channel)
                ->then(function (ChannelInterface $channel) use ($client) {
                    $client->send(":warning: Vous n'êtes pas vivant dans la partie spécifiée.", $channel);
                });
            throw new Exception("Impossible de sonder en étant mort.");
        }

        if ($player->role != Role::SEER) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: Vous n'êtes pas la Voyante dans la partie spécifiée.", $dmc);
                     }
                 );
            throw new Exception("Le joueur n'est pas la Voyante mais essaie de sonder.");
        }

        if (! in_array($this->game->getState(), [GameState::FIRST_NIGHT, GameState::NIGHT])) {
            throw new Exception("Can only See at night.");
        }

        if ($this->game->seerSeen()) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: Vous ne pouvez sonder un joueur qu'une seule fois par nuit.", $dmc);
                     }
                 );
            throw new Exception("Vous ne pouvez sonder un joueur qu'une seule fois par nuit.");
        }
    }

    public function fire()
    {
        $client = $this->client;

        foreach ($this->game->getLivingPlayers() as $player) {
            if (! strstr($this->chosenUserId, $player->getId())) {
                continue;
            }

            if ($player->role == Role::WEREWOLF || $player->role == Role::LYCAN) {
                $msg = "@{$player->getUsername()} est dans le camp des Loups-Garous.";
            } else {
                $msg = "@{$player->getUsername()} est dans le camp des Villageois.";
            }

            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client, $msg) {
                         $this->client->send($msg, $dmc);
                     }
                 );

            $this->game->setSeerSeen(true);

            $this->gameManager->changeGameState($this->game->getId(), GameState::DAY);

            return;
        }

        $this->client->getDMById($this->channel)
             ->then(
                 function (DirectMessageChannel $dmc) use ($client) {
                     $this->client->send("Impossible de trouver le joueur que vous avez demandé.", $dmc);
                 }
             );
    }
}
