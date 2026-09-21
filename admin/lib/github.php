<?php
/**
 * Client minimal pour l'API GitHub (Contents API), utilisé par publier-article.php
 * pour publier les articles de Fanny sans qu'elle ait jamais à voir GitHub.
 *
 * Le jeton vient de admin/cms-token.php (ignoré par git, généré en production
 * par le workflow de déploiement à partir du secret GitHub CMS_GITHUB_TOKEN —
 * voir mail-config.php pour le même principe).
 */

const GH_OWNER = 'meganeedard-netizen';
const GH_REPO = 'lcv-amo';
const GH_BRANCH = 'main';

class GitHubPublishException extends Exception {}

function gh_token() {
    $chemin = __DIR__ . '/../cms-token.php';
    if (!file_exists($chemin)) {
        throw new GitHubPublishException("Configuration manquante (cms-token.php). Contacter le développeur du site.");
    }
    $config = require $chemin;
    if (empty($config['token'])) {
        throw new GitHubPublishException("Jeton GitHub manquant dans la configuration.");
    }
    return $config['token'];
}

/**
 * Crée (ou met à jour, si $sha est fourni) un fichier dans le dépôt via l'API GitHub.
 * $contenu est la chaîne binaire brute du fichier (pas encore en base64).
 */
function gh_put_file($cheminFichier, $contenu, $messageCommit, $sha = null) {
    if (!function_exists('curl_init')) {
        throw new GitHubPublishException("L'extension cURL n'est pas disponible sur le serveur.");
    }

    $url = "https://api.github.com/repos/" . GH_OWNER . "/" . GH_REPO . "/contents/" . rawurlencode_path($cheminFichier);

    $payload = [
        'message' => $messageCommit,
        'content' => base64_encode($contenu),
        'branch' => GH_BRANCH,
    ];
    if ($sha) {
        $payload['sha'] = $sha;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . gh_token(),
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: lcv-amo-publication',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $reponse = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreurCurl = curl_error($ch);
    curl_close($ch);

    if ($reponse === false) {
        throw new GitHubPublishException("Connexion à GitHub impossible : $erreurCurl");
    }
    if ($code < 200 || $code >= 300) {
        $data = json_decode($reponse, true);
        $msg = $data['message'] ?? "erreur inconnue ($code)";
        throw new GitHubPublishException("GitHub a refusé la publication de $cheminFichier : $msg");
    }

    return json_decode($reponse, true);
}

/** Vérifie si un fichier existe déjà dans le dépôt (pour garantir des identifiants uniques). */
function gh_file_exists($cheminFichier) {
    return gh_get_file($cheminFichier) !== null;
}

/**
 * Récupère le contenu décodé d'un fichier du dépôt, et son sha (nécessaire pour le modifier).
 * Retourne null si le fichier n'existe pas.
 */
function gh_get_file($cheminFichier) {
    $reponse = gh_get(GH_OWNER . "/" . GH_REPO . "/contents/" . rawurlencode_path($cheminFichier) . "?ref=" . GH_BRANCH);
    if ($reponse === null) {
        return null;
    }
    return [
        'sha' => $reponse['sha'],
        'contenu' => base64_decode($reponse['content']),
    ];
}

/** Liste les fichiers d'un dossier du dépôt (ex. content/blog). Retourne [] si le dossier n'existe pas. */
function gh_list_dir($cheminDossier) {
    $reponse = gh_get(GH_OWNER . "/" . GH_REPO . "/contents/" . rawurlencode_path($cheminDossier) . "?ref=" . GH_BRANCH);
    if ($reponse === null || !is_array($reponse)) {
        return [];
    }
    return array_values(array_filter($reponse, fn($f) => ($f['type'] ?? '') === 'file'));
}

/** Supprime un fichier du dépôt (utilisé pour effacer un brouillon une fois publié). */
function gh_delete_file($cheminFichier, $sha, $messageCommit) {
    if (!function_exists('curl_init')) {
        throw new GitHubPublishException("L'extension cURL n'est pas disponible sur le serveur.");
    }
    $url = "https://api.github.com/repos/" . GH_OWNER . "/" . GH_REPO . "/contents/" . rawurlencode_path($cheminFichier);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_POSTFIELDS => json_encode(['message' => $messageCommit, 'sha' => $sha, 'branch' => GH_BRANCH]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . gh_token(),
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: lcv-amo-publication',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/** Requête GET générique vers l'API GitHub. Retourne le JSON décodé, ou null en cas de 404. */
function gh_get($cheminApi) {
    if (!function_exists('curl_init')) {
        throw new GitHubPublishException("L'extension cURL n'est pas disponible sur le serveur.");
    }
    $ch = curl_init("https://api.github.com/repos/$cheminApi");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . gh_token(),
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: lcv-amo-publication',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $reponse = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 404) {
        return null;
    }
    if ($code < 200 || $code >= 300) {
        throw new GitHubPublishException("Erreur GitHub (code $code) en lisant $cheminApi.");
    }
    return json_decode($reponse, true);
}

/** URL "brute" GitHub d'un fichier du dépôt — disponible dès le commit, sans attendre le déploiement Hostinger. */
function gh_raw_url($cheminFichier) {
    if (!$cheminFichier) {
        return '';
    }
    return 'https://raw.githubusercontent.com/' . GH_OWNER . '/' . GH_REPO . '/' . GH_BRANCH . '/' . ltrim($cheminFichier, '/');
}

function rawurlencode_path($chemin) {
    return implode('/', array_map('rawurlencode', explode('/', $chemin)));
}
