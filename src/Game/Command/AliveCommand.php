<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slackwolf\Game\Formatter\PlayerListFormatter;
use Slackwolf\Game\Game;

class AliveCommand extends Command
{

    /**
     * @var Game
     */
    private $game;

    public function init()
    {
        $this->game = $this->gameManager->getGame($this->channel);
    }

    public function fire()
    {
        $client = $this->client;

        if ( ! $this->gameManager->hasGame($this->channel)) {
            $client->getChannelGroupOrDMByID($this->channel)
               ->then(function (ChannelInterface $channel) use ($client) {
                   $client->send(":warning: Aucun jeu en cours", $channel);
               });
            return;
        }

        // build list of players
        $playersList = PlayerListFormatter::format($this->game->getLivingPlayers());
        $this->gameManager->sendMessageToChannel($this->game, ":ok: Joueurs encore vivants : ".$playersList);

    }
}
