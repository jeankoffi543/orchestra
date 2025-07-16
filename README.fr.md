[![Tests](https://github.com/jeankoffi543/orchestra/actions/workflows/tests.yml/badge.svg)](https://github.com/jeankoffi543/orchestra/actions)
![Version Packagist](https://img.shields.io/packagist/v/kjos/orchestra)
![PHP](https://img.shields.io/badge/PHP-%5E8.0-blue)
![Licence](https://img.shields.io/github/license/jeankoffi543/orchestra)

## 🌐 Multilingue | Multilingue

- 🇫🇷 [Version Française](README.fr.md)
- 🇬🇧 [English Version](README.md)

## 🇫🇷 Français

# kjos orchestra
Un puissant gestionnaire multi-tenant pour Laravel, avec prise en charge intégrée de l'isolation de domaines, de la séparation de bases de données et de la configuration automatisée des hôtes virtuels Apache.

# Fonctionnalité
`composer require kjos/orchestra`

## Installation du serveur maître
Avant de commencer, vous devez installer le serveur maître.
Nous vous demanderons de configurer l'utilisateur qui sera utilisé pour la création des hôtes virtuels.
Une fois l'utilisateur configuré, une tâche planifiée est créée dans votre table crontab. Vous pouvez saisir « crontab -e » pour la visualiser.
La tâche planifiée utilisera votre utilisateur et ses privilèges pour automatiser la création et la configuration de votre hôte virtuel.
Les hôtes virtuels sont configurés dans le répertoire /etc/apache2/sites-available.

# Concept
kjos/orchestra simplifie la gestion des applications multi-locataires en fournissant :

- Des bases de données permanentes indépendantes

- Des fichiers de routage, de configuration et de stockage distincts pour chaque locataire

- Configuration automatique des hôtes virtuels Apache

- Gestion simplifiée du cycle de vie des locataires via les commandes Artisan

Idéal pour les plateformes SaaS ou les systèmes nécessitant une isolation stricte des locataires.

# Démarrage

## Configuration du locataire principal
Avant d'ajouter des locataires, vous devez installer et configurer le locataire principal.

## Comment se déroule l'installation ?
Vous configurerez un utilisateur système qui gérera les hôtes virtuels Apache.

Une tâche planifiée sera ajoutée à votre crontab pour automatiser la création des hôtes virtuels.

Les hôtes virtuels Apache sont stockés dans :
/etc/apache2/sites-available/

## Étapes d'installation
1. Créer et configurer le locataire principal

2. Publier le fichier de configuration :
```
php artisan vendor:publish --tag=orchestra-config
```
3. Créer la base de données du locataire principal

4. Ajouter le locataire au fichier .tenants à la racine de votre projet

5. Créer le répertoire site/, qui contiendra les sous-dossiers de chaque locataire

6. Lier le répertoire /public de chaque locataire à la racine publique de Laravel

7. Configurer l'hôte virtuel :

- Lien symbolique : /var/www/html/{tenant-domain} → project/site/{tenant-name}

- Configuration Apache : /etc/apache2/sites-available/{tenant-domain}.conf

- Fichier journal : /var/log/apache2/{tenant-domain}.log

8. Activer le virtuel hôte :
```
sudo a2ensite {tenant-domain}.conf
sudo systemctl reload apache2
```
9. Enregistrez le fournisseur de services dans config/app.php :
```
App\Providers\TenantServiceProvider::class,
```

Le fournisseur de services est le noyau central qui initialise et résout le contexte et la configuration spécifiques au locataire.

## Commandes Artisan

### Installer le locataire principal
`php artisan orchestra:install the_master_tenant_name --domain=master_tenant_domain --driver=[mysql|pgsql]`

### ❌ Désinstaller le locataire principal
`php artisan orchestra:uninstall the_master_tenant_name --driver=[mysql|pgsql]`

### ➕ Créer un nouveau locataire
`php artisan orchestra:create the_tenant_name --domain=the_tenant_domain --driver=[mysql|pgsql] --migrate`

### ➖ Supprimer un locataire
`php artisan orchestra:delete the_tenant_name --driver=[mysql|pgsql]`

### 🧾 Options disponibles
| Options    | Description             
|:---------------------------------------------------------------------:|
| `--domain` | Domaine du locataire                                     |
| `--driver` | Pilote de base de données `pgsql` ou `mysql`             |
| `--migrate`| Migrer ou non la nouvelle base de données du locataire   |

#  Command native Laravel
### ➖ Migrate ou seed un tenant spécifique
`php artisan migrate --tenant=tenant_name`
`php artisan db:seed --class=ExempleSeeder --tenant=tenant_name`

### ➖ Migrate or seed ou seed tous les tenant
`php artisan migrate --tenant`
`php artisan db:seed --class=ExempleSeeder --tenant`

### Fonctionnalités supplémentaires
Chaque locataire possède ses propres :

- `routes` (API, web, console)

- `database`

- `storage` et `cache`

- Contexte `config`

Automatisation Crontab pour la gestion des hôtes virtuels

Prise en charge des tests Laravel avec isolation du locataire

Découverte dynamique du locataire via le fichier `.tenants`

.

# 📁 Structure du répertoire
```
project-racine/
├── site/
│ ├── master/
│ │ └── route/
│ └── tenant-a/
│ | └── route/
| |__ tenant-b/
| |__roads/
|
├── .tenants
├── config/orchestra.php
├── app/Providers/TenantServiceProvider.php
```

# 👤 Auteur
Maintenu par [Jean Koffi](https://www.linkedin.com/in/konan-kan-jean-sylvain-koffi-39970399/)

# 📄 Licence
MIT © kjos/orchestra

# 🤝 Appel à contributions
Ce projet est ouvert aux contributions !
Êtes-vous développeur, passionné par Laravel ou intéressé par l'architecture multi-tenant ?

- Dupliquer le projet

- Créer une branche (koor/my-feature)

- Faire une demande de publication 🧪

De nombreuses autres commandes permettent une configuration supplémentaire.