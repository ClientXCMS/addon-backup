# Configuration des Fournisseurs de Sauvegarde

Ce document explique comment configurer chaque type de fournisseur de sauvegarde disponible dans l'addon de backup.

---

## 💡 Concepts Importants : Root vs Sub-folder

Avant de configurer un fournisseur, il est crucial de comprendre la différence entre ces deux réglages :

- **Chemin Racine (Root Path) :** C'est le dossier de base sur votre serveur distant (ex: `/` ou `/var/backups`).
- **Dossier de destination (Sub-folder) :** C'est le sous-dossier **à l'intérieur** de la racine où les fichiers seront stockés. Par défaut, il est réglé sur `backups`.
  - *Astuce :* Si vous voulez sauvegarder exactement à l'endroit défini par le "Chemin Racine", laissez le "Sub-folder" vide.

---

## 💻 1. Local (Stockage sur le serveur actuel)

Utile pour les sauvegardes temporaires ou si vous synchronisez ensuite le dossier manuellement.

- **Root Path :** Le chemin absolu sur votre serveur (ex: `/home/user/backups`). Par défaut : `storage/backups`.
- **Sub-folder :** Le sous-dossier dans ce chemin (ex: `site_web`).

---

## 📁 2. FTP / SFTP

Pour envoyer vos sauvegardes vers un serveur externe.

- **Hôte (Host) :** L'adresse IP ou le nom de domaine du serveur.
- **Utilisateur / Mot de passe :** Vos identifiants de connexion.
- **Port :** 21 pour le FTP, 22 pour le SFTP.
- **Chemin Racine (Root Path) :** Le dossier de départ sur le serveur distant (ex: `/backups`).
- **SSL :** À cocher pour le FTP explicite (FTPS).
- **Sub-folder :** Le sous-dossier utilisé pour ranger les fichiers.

> [!NOTE]
> Le **SFTP** nécessite l'installation du package `league/flysystem-sftp-v3`.

---

## ☁️ 3. S3 (Amazon S3, Minio, DigitalOcean Spaces)

Pour le stockage objet cloud compatible S3.

- **Clé d'accès (Access Key) :** Votre identifiant d'API.
- **Clé Secrète (Secret Key) :** Votre clé secrète d'API.
- **Région :** La région de votre bucket (ex: `us-east-1`, `fr-par`).
- **Bucket :** Le nom de votre bucket.
- **Endpoint (Facultatif) :** À remplir si vous n'utilisez pas Amazon (ex: `https://s3.fr-par.scw.cloud` pour Scaleway).
- **Sub-folder :** Le préfixe (dossier) dans le bucket S3.

---

## 📂 4. Google Drive

Pour sauvegarder sur votre espace Google Drive personnel ou partagé.

- **Client ID / Client Secret :** Obtenus via la Google Cloud Console.
- **Refresh Token :** Jeton permettant l'accès longue durée sans reconnexion.
- **Folder ID (Facultatif) :** L'ID du dossier Google Drive où ranger les fichiers (visible dans l'URL du dossier sur Drive).

> [!NOTE]
> Ce driver nécessite les packages `masbug/flysystem-google-drive-ext` et `google/apiclient`.
