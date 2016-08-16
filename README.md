Baztille API
=====================

Baztille est un mouvement de citoyens pour réinventer la démocratie.
[En savoir plus](http://baztille.org)

[![Télécharger sur le Play Store](https://developer.android.com/images/brand/en_generic_rgb_wo_45.png)](https://play.google.com/store/apps/details?id=org.baztille.app&hl=fr)


## A propos de l'API Baztille

Cet API fourni les données à l'application [cliente Baztille](https://github.com/Baztille/app)

## Dépendances

* PHP (+ Log-Pear)
* MongoDB
* Composer

## Installation

```
# Si Log-Pear n'est pas installé
$ apt-get install php-pear
$ pear install Log
```

```bash
# Cloner le repo
$ git clone git://github.com/Baztille/api.git
$ cd api
$ composer install --prefer-source

# Configurer l'URL de l'API
# Modifier les valeurs du fichier config/config.init.php

```


## Contribuer

1. Forker le projet sur GitHub
2. Créer une branche avec votre correctif (`git checkout -b mon-correctif`)
3. Commit et Push (`git commit -m 'mon super correctif'`)
4. Créer un Pull request sur Github

## Contributeurs

Vous êtes les bienvenues ! 🎉

Le projet est maintenu par : 

* [sourisdudesert](https://github.com/sourisdudesert)
* [citymont](https://github.com/citymont)


## License
[GNU](LICENSE)
