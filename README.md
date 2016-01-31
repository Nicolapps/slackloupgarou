# SlackLoupGarou
SlackLoupGarou est un bot pour Slack. Après avoir invité le bot sur votre channel, vous pouvez jouer aux [Loups-Garous de Thiercelieux](https://fr.wikipedia.org/wiki/Les_Loups-garous_de_Thiercelieux).

![Un max de fun ! :P](http://cdn.nicolapps.ch/images/uploads/1454241003.png)

## Rôles
SlackLoupGarou supporte actuellement la Voyante, le Salvateur, le Simple-Villageois, le Chien (apparaît à la Voyante comme étant un Villageois), le Spectateur (connaît l'identité de la Voyante) et le Tanneur (s'il se fait tuer, il gagne seul). Vous devez être minimum six joueurs pour pouvoir jouer avec des rôles autres que les Loups-Garous/la Voyante/les Simple-Villageois.

## Comment jouer
`/invite` le robot et tapez /help

## Installation
SlackLoupgarou requit PHP 5.5+ et [Composer](https://getcomposer.org/). Il ne fonctionne **pas** avec PHP7 à cause d'une de ses dépendances.

```
git clone https://github.com/Nicolapps/slackloupgarou
cd slackloupgarou
composer install
```

Modifiez le fichier `.env` avec un token de bot de Slack. Obtenez-en un en allant sur l'onglet "Custom Integrations" de la page "Configure Apps" de Slack. Assurez-vous également que le nom du bot corresponde au fichier `.env`.

Pour démarrer le bot, tapez `php bot.php`

## Créateurs

Le bot a été écrit par @chrisgillis et traduit en français par @nicolapps. Pour modifier le code du bot, envoyez une Pull Request sur [le projet original](https://github.com/chrisgillis/slackwolf).

## License

License MIT
