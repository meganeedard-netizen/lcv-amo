# Déploiement du site La Clef de Voûte

Le site est prêt (5 pages + blog + formulaire de contact). Voici les étapes
restantes pour le mettre en ligne sur lcv-amo.fr, hébergé sur Hostinger,
avec déploiement automatique via GitHub.

## 1. Créer le dépôt GitHub

```bash
cd "/Users/meganeprietoblanco/Desktop/CLAUDE/lcv-amo/site"
git init
git add .
git commit -m "Site La Clef de Voûte — version initiale"
```

Créer un dépôt (public ou privé) sur GitHub, ex. `lcv-amo`, puis :

```bash
git remote add origin git@github.com:TON-COMPTE/lcv-amo.git
git branch -M main
git push -u origin main
```

## 2. Récupérer les identifiants FTP Hostinger

Dans hPanel Hostinger → **Fichiers → Comptes FTP** : noter le serveur (ex.
`ftp.lcv-amo.fr` ou une adresse IP), le nom d'utilisateur et le mot de passe
(en créer un dédié si besoin).

Dans le dépôt GitHub → **Settings → Secrets and variables → Actions**,
ajouter 3 secrets :
- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`

À chaque `git push` sur `main`, le site est automatiquement régénéré (blog +
sitemap) puis déployé sur Hostinger via le workflow `.github/workflows/deploy.yml`.

⚠️ Le tout premier déploiement va uploader tous les fichiers dans le
répertoire racine du FTP (généralement `public_html/`) — vérifier dans
hPanel que le dossier FTP par défaut correspond bien à `public_html/` (ou
adapter `server-dir` dans `deploy.yml` sinon).

## 3. Connecter le nom de domaine

Si lcv-amo.fr n'est pas déjà pointé vers l'hébergement Hostinger : dans
hPanel, associer le domaine à l'hébergement, puis (si le domaine est déposé
ailleurs) mettre à jour les serveurs DNS ou l'enregistrement A chez le
registrar pour pointer vers l'IP Hostinger.

## 4. Activer la publication d'articles pour Fanny

Fanny publie ses articles depuis `https://lcv-amo.fr/admin` → son tableau
de bord (mot de passe) → **Publier un article de blog**. Elle ne voit
jamais GitHub : la publication passe en arrière-plan par un jeton GitHub
technique.

À configurer une fois :
1. Créer un *fine-grained personal access token* sur
   https://github.com/settings/personal-access-tokens/new, limité au
   dépôt `lcv-amo`, permission **Contents: Read and write** uniquement.
2. L'ajouter comme secret GitHub du dépôt : **Settings → Secrets and
   variables → Actions → New repository secret**, nom `CMS_GITHUB_TOKEN`.
3. À chaque déploiement, le workflow génère `admin/cms-token.php` à partir
   de ce secret (jamais versionné dans Git — voir `.gitignore`).

Decap CMS reste disponible sur `https://lcv-amo.fr/admin/cms/` pour un
usage plus avancé (connexion GitHub classique) — voir le guide pas-à-pas :
[`oauth-worker/README.md`](oauth-worker/README.md).

## 5. Compléter les informations restantes

Rechercher `[Téléphone à compléter]` et remplacer partout (footer de
chaque page, page contact, mentions légales) par le vrai numéro.

Dans `mentions-legales.html` et `politique-confidentialite.html`,
compléter les champs surlignés en jaune : statut juridique, SIRET, adresse,
hébergeur (une fois Hostinger confirmé, indiquer : *Hostinger International
Ltd., 61 Lordou Vironos Street, 6023 Larnaca, Chypre*).

Dans `index.html`, remplacer `"telephone": "[Téléphone à compléter]"` dans
le bloc JSON-LD une fois le numéro connu.

## 6. Référencement (SEO)

- Créer un compte [Google Search Console](https://search.google.com/search-console),
  valider la propriété de lcv-amo.fr, puis soumettre `https://lcv-amo.fr/sitemap.xml`.
- Créer / revendiquer une fiche **Google Business Profile** pour La Clef de
  Voûte (catégorie : bureau d'études / consultant en bâtiment), avec la zone
  d'intervention Loiret / Yonne / Seine-et-Marne — cela aide beaucoup le
  référencement local, en complément du site.

## 7. Publier un article de blog (au quotidien, pour Fanny)

1. Aller sur `https://lcv-amo.fr/admin`, se connecter avec le mot de passe
   du tableau de bord.
2. Cliquer sur **✍️ Publier un article de blog**.
3. Remplir le formulaire (titre, sous-titre, vignette, jusqu'à 3 photos,
   texte).
4. Cliquer sur **Publier l'article**.

Quelques minutes plus tard, l'article est en ligne automatiquement
(`admin/publier-article.php` publie sur GitHub via l'API → GitHub Actions
régénère le blog → déploiement Hostinger). Fanny ne voit jamais GitHub.

## 8. Tableau de bord (statistiques + demandes de contact)

Accessible sur `https://lcv-amo.fr/admin/tableau-de-bord.php`, protégé par
mot de passe. Depuis cette page, un bouton « Publier un article de blog »
renvoie vers `publier-article.php`.

- **Compteur de visites** : anonyme, sans cookie, alimenté par `track.php`
  (appelé automatiquement par `assets/js/main.js` sur chaque page publique).
- **Historique des demandes** : chaque envoi du formulaire de contact est
  enregistré en plus de l'email (les 500 demandes les plus récentes).
- Les données sont stockées dans `data/visits.json` et `data/contacts.json`
  directement sur le serveur Hostinger (dossier protégé par `.htaccess`,
  jamais versionné dans Git — donc jamais écrasé par un déploiement).
- Le mot de passe est un hash bcrypt dans `admin/auth-config.php` (voir ce
  fichier pour la procédure de changement).
