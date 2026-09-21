<?php
/**
 * Liste des articles de Fanny : publiés (content/blog) et brouillons (content/drafts).
 * Permet de rouvrir n'importe lequel dans publier-article.php pour le modifier,
 * ou de supprimer un brouillon abandonné.
 */

session_start();
require __DIR__ . '/auth-config.php';
require __DIR__ . '/lib/github.php';
require __DIR__ . '/lib/frontmatter.php';

if (empty($_SESSION['lcv_admin'])) {
    header('Location: tableau-de-bord.php');
    exit;
}

function e($texte) {
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

$erreur = '';

// Suppression d'un brouillon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer_brouillon') {
    try {
        $slug = basename($_POST['slug'] ?? '');
        $fichier = gh_get_file("content/drafts/$slug.md");
        if ($fichier) {
            gh_delete_file("content/drafts/$slug.md", $fichier['sha'], "Supprime le brouillon \"$slug\"");
        }
    } catch (GitHubPublishException $e) {
        $erreur = $e->getMessage();
    }
    header('Location: articles.php');
    exit;
}

function charger_liste($dossier) {
    $items = [];
    foreach (gh_list_dir($dossier) as $fichier) {
        if (substr($fichier['name'], -3) !== '.md') {
            continue;
        }
        $contenu = gh_get_file($dossier . '/' . $fichier['name']);
        if (!$contenu) {
            continue;
        }
        $parse = frontmatter_parser($contenu['contenu']);
        $slug = $parse['data']['slug'] ?? basename($fichier['name'], '.md');
        $items[] = [
            'slug' => $slug,
            'titre' => $parse['data']['title'] ?? '(sans titre)',
            'date' => $parse['data']['date'] ?? '',
            'cover' => $parse['data']['cover'] ?? '',
        ];
    }
    usort($items, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $items;
}

$articles = [];
$brouillons = [];
try {
    $articles = charger_liste('content/blog');
    $brouillons = charger_liste('content/drafts');
} catch (GitHubPublishException $e) {
    $erreur = $e->getMessage();
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mes articles | La Clef de Voûte</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="../assets/img/favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --navy: #242E47; --navy-deep: #1A2132; --pink: #862762; --pink-deep: #5F1C45;
    --stone-soft: #E9E8E3; --stone-line: #C7C5BC; --offwhite: #FAF9F6; --white: #FFFFFF;
    --gray-text: #55565C; --font-display: 'Space Grotesk', Arial, sans-serif;
    --font-body: 'Manrope', Arial, sans-serif; --radius: 14px; --radius-sm: 8px;
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: var(--font-body); background: var(--offwhite); color: var(--navy-deep); }
  .wrap { max-width: 860px; margin: 0 auto; padding: 32px 20px 80px; }
  h1 { font-family: var(--font-display); color: var(--navy); font-size: 1.5rem; margin: 0 0 6px; }
  h2 { font-family: var(--font-display); color: var(--navy); font-size: 1.1rem; margin: 32px 0 14px; }
  .topbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 6px; }
  a.retour { color: var(--navy); font-size: 0.88rem; text-decoration: none; font-weight: 600; }
  a.retour:hover { text-decoration: underline; }
  a.btn, button.btn { font-family: var(--font-body); font-weight: 600; font-size: 0.88rem; padding: 9px 16px; border-radius: 999px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
  .btn--pink { background: var(--pink); color: #fff; }
  .btn--pink:hover { background: var(--pink-deep); }
  .btn--ghost { background: transparent; color: var(--navy); border: 1px solid var(--stone-line); }
  .btn--ghost:hover { background: var(--stone-soft); }

  .message { border-radius: var(--radius-sm); padding: 14px 16px; font-size: 0.9rem; margin-bottom: 20px; background: #FBE7EE; color: var(--pink-deep); }
  .vide { color: var(--gray-text); font-style: italic; padding: 10px 0; }

  .carte { display: flex; gap: 16px; align-items: center; background: var(--white); border-radius: var(--radius); padding: 14px; box-shadow: 0 6px 20px -10px rgba(23,26,61,.18); margin-bottom: 12px; }
  .carte img { width: 84px; height: 64px; object-fit: cover; border-radius: var(--radius-sm); flex-shrink: 0; background: var(--stone-soft); }
  .carte .vignette-vide { width: 84px; height: 64px; border-radius: var(--radius-sm); background: var(--stone-soft); flex-shrink: 0; }
  .carte .infos { flex: 1; min-width: 0; }
  .carte .infos strong { display: block; font-size: 0.98rem; color: var(--navy); }
  .carte .infos span { font-size: 0.82rem; color: var(--gray-text); }
  .carte .actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <a class="retour" href="tableau-de-bord.php">← Retour au tableau de bord</a>
  </div>
  <div class="topbar">
    <h1>Mes articles</h1>
    <a href="publier-article.php" class="btn btn--pink">+ Nouvel article</a>
  </div>

  <?php if ($erreur): ?>
    <div class="message">⚠️ <?= e($erreur) ?></div>
  <?php endif; ?>

  <h2>Brouillons <span style="color:var(--gray-text); font-weight:400;">— pas encore en ligne</span></h2>
  <?php if (!$brouillons): ?>
    <p class="vide">Aucun brouillon en cours.</p>
  <?php else: foreach ($brouillons as $b): ?>
    <div class="carte">
      <?php if (gh_raw_url($b['cover'])): ?>
        <img src="<?= e(gh_raw_url($b['cover'])) ?>" alt="">
      <?php else: ?>
        <div class="vignette-vide"></div>
      <?php endif; ?>
      <div class="infos">
        <strong><?= e($b['titre']) ?></strong>
        <span>Brouillon</span>
      </div>
      <div class="actions">
        <a href="publier-article.php?slug=<?= urlencode($b['slug']) ?>&type=draft" class="btn btn--pink">Continuer</a>
        <form method="post" onsubmit="return confirm('Supprimer ce brouillon définitivement ?');" style="margin:0;">
          <input type="hidden" name="action" value="supprimer_brouillon">
          <input type="hidden" name="slug" value="<?= e($b['slug']) ?>">
          <button type="submit" class="btn btn--ghost">Supprimer</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <h2>Articles publiés</h2>
  <?php if (!$articles): ?>
    <p class="vide">Aucun article publié pour le moment.</p>
  <?php else: foreach ($articles as $a): ?>
    <div class="carte">
      <?php if (gh_raw_url($a['cover'])): ?>
        <img src="<?= e(gh_raw_url($a['cover'])) ?>" alt="">
      <?php else: ?>
        <div class="vignette-vide"></div>
      <?php endif; ?>
      <div class="infos">
        <strong><?= e($a['titre']) ?></strong>
        <span><?= e($a['date']) ?></span>
      </div>
      <div class="actions">
        <a href="publier-article.php?slug=<?= urlencode($a['slug']) ?>&type=blog" class="btn btn--pink">Modifier</a>
        <a href="https://lcv-amo.fr/blog/<?= urlencode($a['slug']) ?>.html" target="_blank" rel="noopener" class="btn btn--ghost">Voir en ligne</a>
      </div>
    </div>
  <?php endforeach; endif; ?>

</div>
</body>
</html>
