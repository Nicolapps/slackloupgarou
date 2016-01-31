<?php namespace Slackwolf\Game;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slack\RealTimeClient;
use Slackwolf\Game\Command\Command;
use Slackwolf\Game\Formatter\PlayerListFormatter;
use Slackwolf\Game\Formatter\RoleListFormatter;
use Slackwolf\Game\Formatter\RoleSummaryFormatter;
use Slackwolf\Game\Formatter\VoteSummaryFormatter;
use Slackwolf\Message\Message;
use Slackwolf\Game\OptionsManager;
use Slackwolf\Game\OptionName;

class GameManager
{
    private $games = [];

    private $commandBindings;
    private $client;
    public $optionsManager;
    
    public function __construct(RealTimeClient $client, array $commandBindings)
    {
        $this->commandBindings = $commandBindings;
        $this->client = $client;
        $this->optionsManager = new OptionsManager();
    }
    
    public function input(Message $message)
    {
        $input = $message->getText();

        if ( ! is_string($input)) {
            return false;
        }

        if ( ! isset($input[0])) {
            return false;
        }

        if ($input[0] !== '!') {
            return false;
        }

        $input_array = explode(' ', $input);

        $command = $input_array[0];

        if (strlen($command) < 2) {
            return false;
        }

        $command = substr($command, 1);

        $args = [];

        foreach ($input_array as $i => $arg)
        {
            if ($i == 0) { continue; } // Skip the command

            if (empty($arg)) { continue; }

            $args[] = $arg;
        }

        if ($command == null) {
            return false;
        }

        $command = strtolower($command);

        if ( ! isset($this->commandBindings[$command])) {
            return false;
        }

        try
        {
            /** @var Command $command */
            $command = new $this->commandBindings[$command]($this->client, $this, $message, $args);
            $command->fire();
        } catch (Exception $e)
        {
            return false;
        }

        return true;
    }
    
    public function sendMessageToChannel($game, $msg)
    {
        $client = $this->client;
        $client->getChannelGroupOrDMByID($game->getId())
               ->then(function (ChannelInterface $channel) use ($client,$msg) {
                   $client->send($msg, $channel);
               });
    }

    public function changeGameState($gameId, $newGameState)
    {
        $game = $this->getGame($gameId);

        if ( ! $game) {
            throw new Exception();
        }

        if ($game->isOver()) {
            $this->onGameOver($game);
            return;
        }

        if ($game->getState() == GameState::NIGHT && $newGameState == GameState::DAY) {
            $numSeer = $game->getNumRole(Role::SEER);

            if ($numSeer && ! $game->seerSeen()) {
                return;
            }

            $numWolf = $game->getNumRole(Role::WEREWOLF);

            if ($numWolf && ! $game->getWolvesVoted()) {
                return;
            }

            $numBodyguard = $game->getNumRole(Role::BODYGUARD);

            if ($numBodyguard && ! $game->getGuardedUserId()) {
                return;
            }

            $this->onNightEnd($game);

            if ($game->isOver()) {
                $this->onGameOver($game);
                return;
            }
        }

        $game->changeState($newGameState);

        if ($newGameState == GameState::FIRST_NIGHT) {
            $this->onFirstNight($game);
        }

        if ($newGameState == GameState::DAY) {
            $this->onDay($game);
        }

        if ($newGameState == GameState::NIGHT) {
            $this->onNight($game);
        }
    }

    public function hasGame($id)
    {
        return isset($this->games[$id]);
    }

    /**
     * @param $id
     *
     * @return Game|bool
     */
    public function getGame($id)
    {
        if ($this->hasGame($id)) {
            return $this->games[$id];
        }

        return false;
    }

    public function newGame($id, array $users, $roleStrategy)
    {
        $this->addGame(new Game($id, $users, $roleStrategy));
   }

    public function startGame($id)
    {
        $game = $this->getGame($id);
        if (!$this->hasGame($id)) { return; }
        $users = $game->getLobbyPlayers();
        if(count($users) < 3) {
            $this->sendMessageToChannel($game, "Impossible de démarrer une partie avec moins de trois joueurs.");
            return;
        }
        $game->assignRoles();
        $this->changeGameState($id, GameState::FIRST_NIGHT);
    }
    
    public function endGame($id, $enderUserId = null)
    {
        $game = $this->getGame($id);

        if ( ! $game) {
            return;
        }

        $playerList = RoleSummaryFormatter::format($game->getLivingPlayers(), $game->getOriginalPlayers());

        $client = $this->client;
        $winningTeam = $game->whoWon();

        if($winningTeam !== null) {
            $winMsg = ":clipboard: Résumé des rôles\r\n--------------------------------------------------------------\r\n{$playerList}\r\n\r\n:tada: La partie est terminée !.  ";
            if ($winningTeam == Role::VILLAGER) {
                $winMsg .= "Les Villageois ont gagné !";
            }
            elseif ($winningTeam == Role::WEREWOLF) {
                $winMsg .= "Les Loups-Garous ont gagné !";
            }
            elseif ($winningTeam == Role::TANNER) {
                $winMsg .= "Le Tanneur a gagné !";
            }
            else {
                $winMsg .= "Une équipe inconnue a gagné !";
            }
            $this->sendMessageToChannel($game, $winMsg);
        }

        if ($enderUserId !== null) {
            $client->getUserById($enderUserId)
                   ->then(function (\Slack\User $user) use ($game, $playerList) {
                       $gameMsg = ":triangular_flag_on_post: ";
                       $roleSummary = "";
                       if($game->getState() != GameState::LOBBY) {
                           $gameMsg .= "La partie a été terminée";
                           $roleSummary .= "\r\n\r\nRésumé des rôles:\r\n----------------\r\n{$playerList}";
                       } else {
                           $gameMsg .= "Le lobby a été fermé";
                       }
                       $this->sendMessageToChannel($game, $gameMsg." by @{$user->getUsername()}.".$roleSummary);
                   });
        }

        unset($this->games[$id]);
    }

    public function vote(Game $game, $voterId, $voteForId)
    {
        if ( ! $game->isPlayerAlive($voterId)) {
            return;
        }

        if ( ! $game->isPlayerAlive($voteForId)
                && ($voteForId != 'noone' || !$this->optionsManager->getOptionValue(OptionName::no_lynch))
                && $voteForId != 'clear') {
            return;
        }

        if ($game->hasPlayerVoted($voterId)) {
            //If changeVote is not enabled and player has already voted, do not allow another vote
            if (!$this->optionsManager->getOptionValue(OptionName::changevote))
            {
                throw new Exception("Le changement de vote n'est pas autorisé.");
            }
            $game->clearPlayerVote($voterId);
        }

        if ($voteForId != 'clear') { //if voting for 'clear' just clear vote
            $game->vote($voterId, $voteForId);
        }
        $voteMsg = VoteSummaryFormatter::format($game);

        $this->sendMessageToChannel($game, $voteMsg);
        
        if ( ! $game->votingFinished()) {
            return;
        }

        $votes = $game->getVotes();

        $vote_count = [];
        foreach ($votes as $lynch_player_id => $voters) {
            if ( ! isset($vote_count[$lynch_player_id])) {
                $vote_count[$lynch_player_id] = 0;
            }

            $vote_count[$lynch_player_id] += count($voters);
        }

        $players_to_be_lynched = [];

        $max = 0;
        foreach ($vote_count as $lynch_player_id => $num_votes) {
            if ($num_votes > $max) {
                $max = $num_votes;
            }
        }
        foreach ($vote_count as $lynch_player_id => $num_votes) {
            if ($num_votes == $max && $lynch_player_id != 'noone') {
                $players_to_be_lynched[] = $lynch_player_id;
            }
        }

        $lynchMsg = "\r\n";
        if (count($players_to_be_lynched) == 0){
            $lynchMsg .= ":peace_symbol: Les Villageois ont choisi de ne tuer personne aujourd'hui.";
        }else {
            $lynchMsg .= ":newspaper: Avec leurs fourches, les Villageois ont tué : ";

            $lynchedNames = [];
            foreach ($players_to_be_lynched as $player_id) {
                $player = $game->getPlayerById($player_id);
                $lynchedNames[] = "@{$player->getUsername()} ({$player->role})";
                $game->killPlayer($player_id);
            }

            $lynchMsg .= implode(', ', $lynchedNames). "\r\n";
        }
        $this->sendMessageToChannel($game,$lynchMsg);

        $this->changeGameState($game->getId(), GameState::NIGHT);
    }


    private function addGame(Game $game)
    {
        $this->games[$game->getId()] = $game;
    }

    private function onFirstNight(Game $game)
    {
        $client = $this->client;

        foreach ($game->getLivingPlayers() as $player) {
            $client->getDMByUserId($player->getId())
                ->then(function (DirectMessageChannel $dmc) use ($client,$player,$game) {
                    $client->send("Vous êtes {$player->role}", $dmc);

                    if ($player->role == Role::WEREWOLF) {
                        if ($game->getNumRole(Role::WEREWOLF) > 1) {
                            $werewolves = PlayerListFormatter::format($game->getPlayersOfRole(Role::WEREWOLF));
                            $client->send("Les Loups-Garous sont : {$werewolves}", $dmc);
                        } else {
                            $client->send("Vous êtes le seul Loup-Garou.", $dmc);
                        }
                    }

                    if ($player->role == Role::SEER) {
                        $client->send("C'est à votre tour ! Espionnez un joueur en tapant !see #channel @joueur.\r\nNE DITES PAS PENDANT LA NUIT CE QUE VOUS AVEZ VU, PARLEZ UNIQUEMENT PENDANT LE JOUR ET SI VOUS N'ÊTES PAS MORT(E) !", $dmc);
                    }

                    if ($player->role == Role::BEHOLDER) {
                        $seers = $game->getPlayersOfRole(Role::SEER);
                        $seers = PlayerListFormatter::format($seers);

                        $client->send("La Voyante est: {$seers}", $dmc);
                    }
                });
        }

        $playerList = PlayerListFormatter::format($game->getLivingPlayers());
        $roleList = RoleListFormatter::format($game->getLivingPlayers());

        $msg = ":wolf: Une nouvelle partie de Loups-Garous a commencé ! Pour un tutoriel, tapez !help.\r\n\r\n";
        $msg .= "Joueurs: {$playerList}\r\n";
        $msg .= "Rôles possibles: {$game->getRoleStrategy()->getRoleListMsg()}\r\n\r\n";

        if ($this->optionsManager->getOptionValue(OptionName::role_seer)) {
            $msg .= ":crescent_moon: :zzz: C'est la nuit, le village dort.";
            $msg .= " La partie commencera quand la Voyante aura sondé quelqu'un.";
        }
        $this->sendMessageToChannel($game, $msg);
        
        if (!$this->optionsManager->getOptionValue(OptionName::role_seer)) {
            $this->changeGameState($game->getId(), GameState::NIGHT);        
        }
    }

    private function onDay(Game $game)
    {
        $remainingPlayers = PlayerListFormatter::format($game->getLivingPlayers());

        $dayBreakMsg = ":sunrise: C'est le jour, les villageois se réveillent.\r\n";
        $dayBreakMsg .= "Joueurs restant : {$remainingPlayers}\r\n\r\n";
        $dayBreakMsg .= "Villageois, trouvez les Loups-Garous ! Tapez !vote @joueur pour votez le joueur à lyncher.";
        if ($this->optionsManager->getOptionValue(OptionName::changevote))
        {
            $dayBreakMsg .= "\r\nVous pouvez changer votre vote tant que le vote n'est pas fini. Tapez !vote clear pour retirer votre vote.";
        }
        if ($this->optionsManager->getOptionValue(OptionName::no_lynch))
        {
            $dayBreakMsg .= "\r\Tapez !vote noone pour voter pour que personne ne meure.";
        }

        $this->sendMessageToChannel($game, $dayBreakMsg);
    }

    private function onNight(Game $game)
    {
        $client = $this->client;
        $nightMsg = ":crescent_moon: :zzz: La nuit tombe et tous les villageois vont dormir.";
        $this->sendMessageToChannel($game, $nightMsg);

        $wolves = $game->getPlayersOfRole(Role::WEREWOLF);

        $wolfMsg = ":crescent_moon: C'est la nuit, et il est temps de dévorer un Villageois ! Tapez !kill #channel @joueur pour faire votre choix. ";

        foreach ($wolves as $wolf)
        {
             $this->client->getDMByUserId($wolf->getId())
                  ->then(function (DirectMessageChannel $channel) use ($client,$wolfMsg) {
                      $client->send($wolfMsg, $channel);
                  });
        }

        $seerMsg = ":mag_right: Voyante, sondez un joueur en tapant !see #channel @joueur.";

        $seers = $game->getPlayersOfRole(Role::SEER);

        foreach ($seers as $seer)
        {
            $this->client->getDMByUserId($seer->getId())
                 ->then(function (DirectMessageChannel $channel) use ($client,$seerMsg) {
                     $client->send($seerMsg, $channel);
                 });
        }

        $bodyGuardMsg = ":muscle: Salvateur, vous pouvez chaque nuit protéger un joueur de l'attaque des Loups-Garous. Tapez !guard #channel @joueur";

        $bodyguards = $game->getPlayersOfRole(Role::BODYGUARD);

        foreach ($bodyguards as $bodyguard) {
            $this->client->getDMByUserId($bodyguard->getId())
                 ->then(function (DirectMessageChannel $channel) use ($client,$bodyGuardMsg) {
                     $client->send($bodyGuardMsg, $channel);
                 });
        }
    }

    private function onNightEnd(Game $game)
    {
        $votes = $game->getVotes();

        foreach ($votes as $lynch_id => $voters) {
            $player = $game->getPlayerById($lynch_id);

            if ($lynch_id == $game->getGuardedUserId()) {
                $killMsg = ":muscle: @{$player->getUsername()} a été protégé par le Salvateur de l'attaque des Loups-Garous.";
            } else {
                $killMsg = ":skull_and_crossbones: @{$player->getUsername()} ($player->role) a été tué pendant la nuit.";
                $game->killPlayer($lynch_id);
            }

            $game->setLastGuardedUserId($game->getGuardedUserId());
            $game->setGuardedUserId(null);
            $this->sendMessageToChannel($game, $killMsg);
        }
    }

    private function onGameOver(Game $game)
    {
        $game->changeState(GameState::OVER);
        $this->endGame($game->getId());
    }
}
