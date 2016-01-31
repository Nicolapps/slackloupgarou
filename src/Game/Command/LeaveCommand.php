<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Formatter\PlayerListFormatter;

class LeaveCommand extends Command
{
    private $game;

    public function init()
    {
        if ($this->channel[0] == 'D') {
            throw new Exception("Impossible de quitter une partie ou un lobby par message privé.");
        }

        $this->game = $this->gameManager->getGame($this->channel);

        if ( ! $this->game) {
            throw new Exception("Aucun jeu en cours");
        }
        
        if ($this->game->getState() != GameState::LOBBY) { 
            throw new Exception("Le jeu actuel n'est pas ou plus un lobby.");
        }
    }

    public function fire()
    {
        $this->game->removeLobbyPlayer($this->userId);
            
        $playersList = PlayerListFormatter::format($this->game->getLobbyPlayers());
        $this->gameManager->sendMessageToChannel($this->game, "Lobby actuel : ".$playersList);    
    }
}
