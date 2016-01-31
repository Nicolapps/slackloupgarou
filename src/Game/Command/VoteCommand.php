<?php namespace Slackwolf\Game\Command;

use Exception;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\GameState;
use Zend\Loader\Exception\InvalidArgumentException;

class VoteCommand extends Command
{
    private $game;

    public function init()
    {
        if ($this->channel[0] == 'D') {
            throw new Exception("Vous ne pouvez pas voter par message privé.");
        }

        if (count($this->args) < 1) {
            throw new InvalidArgumentException("Vous devez spécifier un joueur");
        }

        $this->game = $this->gameManager->getGame($this->channel);

        if ( ! $this->game) {
            throw new Exception("Aucun jeu en cours");
        }

        if ($this->game->getState() != GameState::DAY) {
            throw new Exception("Vous ne pouvez que voter pendant le jour.");
        }

        // Voter should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            throw new Exception("Vous ne pouvez pas voter car vous êtes mort.");
        }

        $this->args[0] = UserIdFormatter::format($this->args[0], $this->game->getOriginalPlayers());
echo $this->args[0];
        // Person player is voting for should also be alive
        if ( ! $this->game->isPlayerAlive($this->args[0])
                && $this->args[0] != 'noone'
                && $this->args[0] != 'clear') {
            echo 'not found';
            throw new Exception("Le joueur que vous avez voté n'est pas dans la partie.");
        }
    }

    public function fire()
    {
        $this->gameManager->vote($this->game, $this->userId, $this->args[0]);
    }
}
