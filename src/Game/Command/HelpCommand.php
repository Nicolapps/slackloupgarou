<?php namespace Slackwolf\Game\Command;

use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;

class HelpCommand extends Command
{
    public function fire()
    {
        $client = $this->client;

        $help_msg =  "Comment jouer aux Loups-Garous\r\n------------------------\r\n";
        $help_msg .= "Les Loups-Garous est un jeu de société de déduction. Les joueurs reçoivent au début de la partie leur rôle par message privé. ";
        $help_msg .= "Si vous êtes Villageois, vous devez trouver qui sont les Loups-Garous en vous basant sur les votes et paroles des autres joueurs. ";
        $help_msg .= "Si vous êtes Loup-Garou, vous devez vous faire passer pour un honnête Villageois en mentant du mieux que vous pouvez.\r\n";
        $help_msg .= "La partie se déroule pendant plusieurs jours et nuit. Chaque jour, tous les joueurs votent le joueur à lyncher. Le joueur qui reçoit le plus de votes est lynché. Si il y a une égalité, les joueurs à égalité sont lynchés. ";
        $help_msg .= "Chaque nuit, les Loups-Garous voteront ensemble le Villageois qu'ils vont dévorer. La décision doit être unanime. Si elle ne l'est pas, les Loups-Garous votent jusqu'à qu'elle le soit.\r\n";
        $help_msg .= "Les Villageois gagnent si ils éliminent tous les Loups-Garous. Les Loups-Garous gagnent si ils sont autant ou plus nombreux que les Villageois.\r\n\r\n";
        $help_msg .= "Rôles spéciaux\r\n------------------------\r\n";
        $help_msg .= " |_ La Voyante - Un Villageois qui peut, chaque nuit, voir le rôle d'un autre joueur.\r\n";
        $help_msg .= " |_ Le Tanneur - Un joueur ni Villageois ni Loup-Garou qui gagne si il se fait tuer.\r\n";
        $help_msg .= " |_ Le Chien - Un Villageois qui apparaît à la Voyante comme étant un Loup-Garou.\r\n";
        $help_msg .= " |_ Le Spectateur - Un Villageois qui apprend qui est la Voyante la première nuit.\r\n";
        $help_msg .= " |_ Le Salvateur - Un Villageois qui peut protéger chaque nuit un joueur de l'attaque des Loups-Garous. Il peut se protéger lui-même, mais il ne peut pas protéger le même joueur deux nuits de suite.\r\n\r\n";
        $help_msg .= "Commandes disponibles\r\n------------------------\r\n";
        $help_msg .= "|_  !new - Créer un nouveau lobby que les joueurs pourront !join pour la prochaine partie.\r\n";
        $help_msg .= "|_  !join - Rejoindre un lobby pour la prochaine partie.\r\n";
        $help_msg .= "|_  !leave - Quitter le lobby pour la prochaine partie\r\n";
        $help_msg .= "|_  !start - Commencer la partie. Si aucun paramètre n'est renseigné, les joueurs dans le lobby sont utilisés.\r\n";
        $help_msg .= "|_  !start all - Commencer une nouvelle partie avec tous les utilisateurs présents dans le channel.\r\n";
        $help_msg .= "|_  !start @joueur1 @joueur2 @joueur3 - Commencer une nouvelle partie en spécifiant les joueurs.\r\n";
        $help_msg .= "|_  !vote @joueur|noone|clear - Pendant le jour, votez pour un @joueur, votez pour personne, ou supprimer votre vote existant (l'option « Changement de vote » doit être activée).\r\n";
        $help_msg .= "|_  !see #channel @joueur - Pour la Voyante uniquement (à envoyer par message privé au bot). Permet de voir si le @joueur est Villageois ou Loup-Garou. #channel est le nom du channel où vous jouez.\r\n";
        $help_msg .= "|_  !kill #channel @joueur - Pour les Loups-Garous uniquement (à envoyer par message privé au bot). Prmet de tuer un joueur chaque nuit. Doit être unanime avec tous les autres Loups.\r\n";
        $help_msg .= "|_  !guard #channel @joueur - Pour le Salvateur uniquement. Le Salvateur peut protéger un joueur de l'attaque des Loups-Garous. Il ne peut pas protéger le même joueur deux nuits de suite.\r\n";
        $help_msg .= "|_  !end - Terminer la partie immédiatement.\r\n";
        $help_msg .= "|_  !setoption - Voir ou changer des options. Utilisez sans aucun paramètre pour voir toutes les options disponibles et leurs valeurs actuelles.\r\n";
        $help_msg .= "|_  !dead - Afficher les joueurs morts.\r\n";
        $help_msg .= "|_  !alive - Afficher les joueurs vivants\r\n";

        $this->client->getDMByUserId($this->userId)->then(function(DirectMessageChannel $dm) use ($client, $help_msg) {
            $client->send($help_msg, $dm);
        });
        
        if ($this->channel[0] != 'D') {
            $client->getChannelGroupOrDMByID($this->channel)
               ->then(function (ChannelInterface $channel) use ($client) {
                   $client->send(":book: Regardez vos messages privés pour le texte d'aide.", $channel);
               });
        }
    }
}
