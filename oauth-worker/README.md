# Relais OAuth pour /admin (Decap CMS)

Le site est hébergé sur Hostinger, qui ne peut pas gérer la connexion sécurisée
entre `/admin` et GitHub. Ce petit service gratuit (Cloudflare Worker) sert
uniquement de relais pour cette connexion — c'est une étape technique à faire
une seule fois, au moment de la mise en ligne.

## 1. Créer l'application OAuth sur GitHub

1. Aller sur https://github.com/settings/developers → **New OAuth App**.
2. Renseigner :
   - **Application name** : `La Clef de Voûte — Admin`
   - **Homepage URL** : `https://lcv-amo.fr`
   - **Authorization callback URL** : `https://lcv-amo-cms-auth.<ton-sous-domaine>.workers.dev/callback`
     (l'URL exacte sera connue après l'étape 2 — on peut la mettre à jour ensuite)
3. Cliquer sur **Register application**.
4. Noter le **Client ID**, puis générer un **Client secret** (bouton *Generate a new client secret*) et le noter aussi — il ne sera plus jamais réaffiché.

## 2. Déployer le worker Cloudflare (gratuit)

Prérequis : un compte Cloudflare gratuit (https://dash.cloudflare.com/sign-up), et Node.js installé.

```bash
cd oauth-worker
npx wrangler login          # ouvre le navigateur pour se connecter à Cloudflare
npx wrangler secret put GITHUB_CLIENT_ID
npx wrangler secret put GITHUB_CLIENT_SECRET
npx wrangler deploy
```

La commande `deploy` affiche l'URL du worker, du type :
`https://lcv-amo-cms-auth.<ton-compte>.workers.dev`

## 3. Finaliser la configuration

1. Retourner dans l'OAuth App GitHub (étape 1) et vérifier/corriger la
   **Authorization callback URL** avec l'URL réelle du worker + `/callback`.
2. Dans `admin/config.yml`, remplacer :
   - `repo: TON-COMPTE-GITHUB/lcv-amo` par le vrai dépôt (ex: `meganepb/lcv-amo`)
   - `base_url: https://TON-WORKER.workers.dev` par l'URL du worker (sans `/callback`)
3. Commit + push ces changements.

## 4. Tester

Aller sur `https://lcv-amo.fr/admin/` → cliquer sur **Login with GitHub** →
autoriser l'application → l'interface Decap CMS doit s'ouvrir, avec la liste
des articles du blog.

Seuls les comptes GitHub ayant un accès en écriture au dépôt (collaborateurs)
peuvent se connecter et publier.
