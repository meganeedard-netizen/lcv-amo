<?php
/**
 * Tableau de bord privé — La Clef de Voûte.
 * Statistiques de visites + historique des demandes reçues via le formulaire de contact.
 * Protégé par mot de passe (voir auth-config.php).
 */

session_start();
require __DIR__ . '/auth-config.php';

$erreur = '';

if (isset($_GET['deconnexion'])) {
    unset($_SESSION['lcv_admin']);
    session_destroy();
    header('Location: tableau-de-bord.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mot_de_passe'])) {
    if (password_verify($_POST['mot_de_passe'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['lcv_admin'] = true;
    } else {
        sleep(1); // ralentit les tentatives répétées
        $erreur = 'Mot de passe incorrect.';
    }
}

$connecte = !empty($_SESSION['lcv_admin']);

function chargerJson($chemin, $defaut) {
    if (!file_exists($chemin)) {
        return $defaut;
    }
    $data = json_decode(file_get_contents($chemin), true);
    return is_array($data) ? $data : $defaut;
}

function e($texte) {
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tableau de bord | La Clef de Voûte</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="../assets/img/favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --navy: #242E47;
    --navy-deep: #1A2132;
    --pink: #862762;
    --pink-deep: #5F1C45;
    --violet: #6E4DA8;
    --violet-deep: #52397F;
    --stone-soft: #E9E8E3;
    --stone-line: #C7C5BC;
    --offwhite: #FAF9F6;
    --white: #FFFFFF;
    --gray-text: #55565C;
    --font-display: 'Space Grotesk', Arial, sans-serif;
    --font-body: 'Manrope', Arial, sans-serif;
    --radius: 14px;
    --radius-sm: 8px;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: var(--font-body);
    background: var(--offwhite);
    color: var(--navy-deep);
  }
  .wrap { max-width: 1080px; margin: 0 auto; padding: 32px 20px 80px; }
  h1, h2 { font-family: var(--font-display); color: var(--navy); }
  h1 { font-size: 1.6rem; margin: 0; }
  h2 { font-size: 1.1rem; margin: 0 0 16px; }
  .topbar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 28px;
  }
  .topbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  a.btn, button.btn {
    font-family: var(--font-body); font-weight: 600; font-size: 0.9rem;
    padding: 10px 18px; border-radius: 999px; border: none; cursor: pointer;
    text-decoration: none; display: inline-block;
  }
  .btn--pink { background: var(--pink); color: #fff; }
  .btn--pink:hover { background: var(--pink-deep); }
  .btn--ghost { background: transparent; color: var(--navy); border: 1px solid var(--stone-line); }
  .btn--ghost:hover { background: var(--stone-soft); }
  .btn--violet { background: var(--violet); color: #fff; }
  .btn--violet:hover { background: var(--violet-deep); }

  .login-box {
    max-width: 380px; margin: 12vh auto; background: var(--white);
    padding: 32px; border-radius: var(--radius); box-shadow: 0 12px 32px -16px rgba(23,26,61,.25);
  }
  .login-box input {
    width: 100%; padding: 12px 14px; margin: 14px 0; border-radius: var(--radius-sm);
    border: 1px solid var(--stone-line); font-family: var(--font-body); font-size: 1rem;
  }
  .erreur { color: var(--pink-deep); font-size: 0.9rem; margin: 0; }

  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 28px; }
  .card {
    background: var(--white); border-radius: var(--radius); padding: 18px;
    box-shadow: 0 6px 20px -10px rgba(23,26,61,.18);
  }
  .card .valeur { font-family: var(--font-display); font-size: 1.8rem; color: var(--pink); }
  .card .label { font-size: 0.82rem; color: var(--gray-text); }

  .panel {
    background: var(--white); border-radius: var(--radius); padding: 22px;
    box-shadow: 0 6px 20px -10px rgba(23,26,61,.18); margin-bottom: 24px;
  }

  .barres { display: flex; flex-direction: column; gap: 6px; }
  .barre-ligne { display: grid; grid-template-columns: 90px 1fr 40px; align-items: center; gap: 10px; font-size: 0.82rem; }
  .barre-fond { background: var(--stone-soft); border-radius: 6px; overflow: hidden; height: 10px; }
  .barre-remplie { background: var(--pink); height: 100%; border-radius: 6px; }

  table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
  th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--stone-soft); vertical-align: top; }
  th { color: var(--gray-text); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: .03em; }
  td.message { max-width: 320px; }
  details summary { cursor: pointer; color: var(--pink); font-weight: 600; }
  .vide { color: var(--gray-text); font-style: italic; padding: 8px 0; }
  .pages-list { list-style: none; padding: 0; margin: 0; font-size: 0.88rem; }
  .pages-list li { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--stone-soft); }
</style>
</head>
<body>

<?php if (!$connecte): ?>

  <div class="login-box">
    <h1 style="margin-bottom:6px;">Tableau de bord</h1>
    <p style="color:var(--gray-text); font-size:0.9rem; margin-top:0;">La Clef de Voûte</p>
    <?php if ($erreur): ?><p class="erreur"><?= e($erreur) ?></p><?php endif; ?>
    <form method="post">
      <input type="password" name="mot_de_passe" placeholder="Mot de passe" required autofocus>
      <button type="submit" class="btn btn--pink" style="width:100%;">Se connecter</button>
    </form>
  </div>

<?php else:

  $visites = chargerJson(__DIR__ . '/../data/visits.json', ['total' => 0, 'by_day' => [], 'by_page' => []]);
  $demandes = chargerJson(__DIR__ . '/../data/contacts.json', []);

  $parJour = $visites['by_day'] ?? [];
  krsort($parJour); // du plus récent au plus ancien
  $derniersJours = array_slice($parJour, 0, 14, true);
  $maxJour = $derniersJours ? max($derniersJours) : 1;

  $aujourdhui = date('Y-m-d');
  $visitesAujourdhui = $visites['by_day'][$aujourdhui] ?? 0;

  $visites7j = 0;
  for ($i = 0; $i < 7; $i++) {
    $j = date('Y-m-d', strtotime("-$i day"));
    $visites7j += $visites['by_day'][$j] ?? 0;
  }

  $parPage = $visites['by_page'] ?? [];
  arsort($parPage);
  $topPages = array_slice($parPage, 0, 6, true);
  ?>

  <div class="wrap">

    <div class="topbar">
      <h1>Tableau de bord — La Clef de Voûte</h1>
      <div class="topbar-actions">
        <a href="../index.html" class="btn btn--ghost" target="_blank" rel="noopener">Voir le site</a>
        <a href="articles.php" class="btn btn--violet">✍️ Mes articles de blog</a>
        <a href="?deconnexion=1" class="btn btn--ghost">Déconnexion</a>
      </div>
    </div>

    <div class="cards">
      <div class="card"><div class="valeur"><?= (int)($visites['total'] ?? 0) ?></div><div class="label">Visites totales</div></div>
      <div class="card"><div class="valeur"><?= (int)$visitesAujourdhui ?></div><div class="label">Aujourd'hui</div></div>
      <div class="card"><div class="valeur"><?= (int)$visites7j ?></div><div class="label">7 derniers jours</div></div>
      <div class="card"><div class="valeur"><?= count($demandes) ?></div><div class="label">Demandes reçues</div></div>
    </div>

    <div class="panel">
      <h2>Visites — 14 derniers jours</h2>
      <?php if ($derniersJours): ?>
        <div class="barres">
          <?php foreach ($derniersJours as $jour => $nb): ?>
            <div class="barre-ligne">
              <span><?= e(date('d/m/Y', strtotime($jour))) ?></span>
              <span class="barre-fond"><span class="barre-remplie" style="width:<?= (int) round($nb / $maxJour * 100) ?>%;"></span></span>
              <span><?= (int) $nb ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="vide">Pas encore de visites enregistrées.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Pages les plus consultées</h2>
      <?php if ($topPages): ?>
        <ul class="pages-list">
          <?php foreach ($topPages as $page => $nb): ?>
            <li><span><?= e($page) ?></span><strong><?= (int) $nb ?></strong></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="vide">Pas encore de données.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Demandes reçues via le formulaire de contact</h2>
      <?php if ($demandes): ?>
        <table>
          <thead>
            <tr>
              <th>Date</th><th>Nom</th><th>Structure</th><th>Contact</th><th>Type de projet</th><th>Message</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($demandes as $d): ?>
              <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($d['date'] ?? 'now'))) ?></td>
                <td><?= e($d['nom'] ?? '') ?></td>
                <td><?= e($d['structure'] ?? '') ?></td>
                <td>
                  <a href="mailto:<?= e($d['email'] ?? '') ?>"><?= e($d['email'] ?? '') ?></a><br>
                  <?= e($d['telephone'] ?? '') ?>
                </td>
                <td><?= e($d['type_projet'] ?? '') ?></td>
                <td class="message">
                  <details>
                    <summary>Lire</summary>
                    <p><?= nl2br(e($d['message'] ?? '')) ?></p>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="vide">Aucune demande reçue pour le moment.</p>
      <?php endif; ?>
    </div>

  </div>

<?php endif; ?>

</body>
</html>
