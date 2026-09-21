<?php
/**
 * Publication d'un article de blog par Fanny — sans jamais passer par GitHub.
 * Protégé par la même session que tableau-de-bord.php.
 *
 * Au clic sur "Publier" : les photos sont redimensionnées/compressées, puis
 * le fichier Markdown de l'article et ses images sont envoyés sur GitHub via
 * l'API (jeton technique dans cms-token.php, jamais visible ici). Le
 * déploiement automatique existant (GitHub Actions) prend ensuite le relais :
 * l'article est en ligne quelques minutes plus tard.
 */

session_start();
require __DIR__ . '/auth-config.php';
require __DIR__ . '/lib/github.php';
require __DIR__ . '/lib/image.php';
require __DIR__ . '/lib/texte.php';

if (empty($_SESSION['lcv_admin'])) {
    header('Location: tableau-de-bord.php');
    exit;
}

function e($texte) {
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

/** Répartit les photos entre les paragraphes du texte, de façon régulière. */
function inserer_photos(array $paragraphesTexte, array $urlsPhotos) {
    $n = count($paragraphesTexte);
    $k = count($urlsPhotos);
    if ($k === 0 || $n === 0) {
        return array_merge($paragraphesTexte, array_map(fn($u) => "![]($u)", $urlsPhotos));
    }

    $positions = [];
    for ($i = 1; $i <= $k; $i++) {
        $pos = (int) round($n * $i / ($k + 1));
        $pos = max(1, min($n, $pos));
        while (in_array($pos, $positions, true) && $pos < $n) {
            $pos++;
        }
        $positions[] = $pos;
    }

    $resultat = [];
    foreach ($paragraphesTexte as $index => $bloc) {
        $resultat[] = $bloc;
        $numero = $index + 1;
        $i = array_search($numero, $positions, true);
        if ($i !== false) {
            $resultat[] = "![](" . $urlsPhotos[$i] . ")";
        }
    }
    return $resultat;
}

function fichier_upload_valide($champ) {
    return isset($_FILES[$champ]) && is_uploaded_file($_FILES[$champ]['tmp_name']) && $_FILES[$champ]['error'] === UPLOAD_ERR_OK;
}

$erreur = '';
$succes = null;
$valeurs = ['titre' => '', 'sous_titre' => '', 'texte' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $erreur = "Les photos envoyées sont trop volumineuses pour le serveur. Réessaie avec des photos plus légères, ou moins nombreuses à la fois.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs['titre'] = trim($_POST['titre'] ?? '');
    $valeurs['sous_titre'] = trim($_POST['sous_titre'] ?? '');
    $valeurs['texte'] = trim($_POST['texte'] ?? '');

    try {
        if ($valeurs['titre'] === '') {
            throw new Exception("Le titre est obligatoire.");
        }
        if ($valeurs['texte'] === '') {
            throw new Exception("Le texte de l'article est obligatoire.");
        }
        if (!fichier_upload_valide('vignette')) {
            throw new Exception("La photo de vignette est obligatoire.");
        }

        $slug = slugifier($valeurs['titre']);
        if ($slug === '') {
            throw new Exception("Le titre doit contenir au moins une lettre ou un chiffre.");
        }
        $slugOriginal = $slug;
        $compteur = 2;
        while (gh_file_exists("content/blog/$slug.md")) {
            $slug = "$slugOriginal-$compteur";
            $compteur++;
        }

        // Vignette
        $donneesVignette = image_vers_jpeg_optimise($_FILES['vignette']['tmp_name']);
        $cheminVignette = "content/uploads/$slug-vignette.jpg";
        gh_put_file($cheminVignette, $donneesVignette, "Ajoute la vignette de l'article \"{$valeurs['titre']}\"");

        // Jusqu'à 3 photos dans le corps de l'article
        $urlsPhotos = [];
        foreach (['photo1', 'photo2', 'photo3'] as $i => $champ) {
            if (fichier_upload_valide($champ)) {
                $donnees = image_vers_jpeg_optimise($_FILES[$champ]['tmp_name']);
                $chemin = "content/uploads/$slug-photo" . ($i + 1) . ".jpg";
                gh_put_file($chemin, $donnees, "Ajoute une photo de l'article \"{$valeurs['titre']}\"");
                $urlsPhotos[] = "/$chemin";
            }
        }

        // Corps du texte, avec les photos réparties dedans
        $paragraphesTexte = paragraphes($valeurs['texte']);
        $blocs = inserer_photos($paragraphesTexte, $urlsPhotos);
        $corps = implode("\n\n", $blocs);

        $resume = resume_court($valeurs['sous_titre'] !== '' ? $valeurs['sous_titre'] : $valeurs['texte']);
        $date = date('Y-m-d');

        $frontmatterYaml = "---\n"
            . 'title: "' . yaml_valeur($valeurs['titre']) . "\"\n"
            . 'slug: "' . $slug . "\"\n"
            . 'date: ' . $date . "\n"
            . 'category: "Actualités"' . "\n"
            . 'excerpt: "' . yaml_valeur($resume) . "\"\n"
            . 'cover: "' . $cheminVignette . "\"\n";
        if ($valeurs['sous_titre'] !== '') {
            $frontmatterYaml .= 'subtitle: "' . yaml_valeur($valeurs['sous_titre']) . "\"\n";
        }
        $frontmatterYaml .= "---\n\n" . $corps . "\n";

        gh_put_file("content/blog/$slug.md", $frontmatterYaml, "Publie l'article \"{$valeurs['titre']}\"");

        $succes = $slug;
        $valeurs = ['titre' => '', 'sous_titre' => '', 'texte' => ''];

    } catch (GitHubPublishException $e) {
        $erreur = $e->getMessage();
    } catch (ImageTraitementException $e) {
        $erreur = $e->getMessage();
    } catch (Exception $e) {
        $erreur = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Publier un article | La Clef de Voûte</title>
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
  .wrap { max-width: 720px; margin: 0 auto; padding: 32px 20px 80px; }
  h1 { font-family: var(--font-display); color: var(--navy); font-size: 1.5rem; margin: 0 0 6px; }
  .sous-titre-page { color: var(--gray-text); font-size: 0.92rem; margin: 0 0 26px; }
  .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
  a.retour { color: var(--navy); font-size: 0.88rem; text-decoration: none; font-weight: 600; }
  a.retour:hover { text-decoration: underline; }

  .panel { background: var(--white); border-radius: var(--radius); padding: 26px; box-shadow: 0 6px 20px -10px rgba(23,26,61,.18); }
  label { display: block; font-weight: 600; font-size: 0.9rem; margin: 20px 0 6px; color: var(--navy); }
  label:first-of-type { margin-top: 0; }
  .aide { font-weight: 400; color: var(--gray-text); font-size: 0.82rem; margin-top: 2px; }
  input[type=text], textarea {
    width: 100%; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--stone-line);
    font-family: var(--font-body); font-size: 1rem; background: var(--offwhite);
  }
  textarea { min-height: 260px; resize: vertical; line-height: 1.5; }
  input[type=file] { width: 100%; padding: 10px; border-radius: var(--radius-sm); border: 1px dashed var(--stone-line); background: var(--offwhite); font-size: 0.9rem; }
  .photos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
  .photos-grid > div { margin-top: 0; }

  button.btn { font-family: var(--font-body); font-weight: 700; font-size: 1rem; padding: 13px 24px; border-radius: 999px; border: none; cursor: pointer; background: var(--pink); color: #fff; margin-top: 12px; width: 100%; }
  button.btn:hover { background: var(--pink-deep); }
  button.btn:disabled { opacity: 0.6; cursor: wait; }
  button.btn--ghost { background: transparent; color: var(--navy); border: 1px solid var(--stone-line); }
  button.btn--ghost:hover { background: var(--stone-soft); }
  .actions { margin-top: 24px; }

  .message { border-radius: var(--radius-sm); padding: 14px 16px; font-size: 0.9rem; margin-bottom: 20px; }
  .message--erreur { background: #FBE7EE; color: var(--pink-deep); }
  .message--succes { background: #E9F5EC; color: #1E6B3A; }
  .message--succes a { color: inherit; font-weight: 700; }

  dialog#apercu { padding: 0; border: none; border-radius: var(--radius); width: min(900px, 92vw); height: 88vh; box-shadow: 0 20px 60px -20px rgba(23,26,61,.4); }
  dialog#apercu::backdrop { background: rgba(26,33,50,.55); }
  .apercu-barre { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; background: var(--navy); color: #fff; font-size: 0.85rem; font-weight: 600; }
  .apercu-barre button { font-family: var(--font-body); background: rgba(255,255,255,.15); color: #fff; border: none; border-radius: 999px; padding: 6px 14px; cursor: pointer; font-size: 0.82rem; }
  .apercu-barre button:hover { background: rgba(255,255,255,.25); }
  #apercu iframe { width: 100%; height: calc(88vh - 46px); border: none; display: block; }
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <a class="retour" href="tableau-de-bord.php">← Retour au tableau de bord</a>
  </div>
  <h1>Publier un article de blog</h1>
  <p class="sous-titre-page">La mise en page est automatique, reprend le style du site. L'article est en ligne quelques minutes après la publication.</p>

  <?php if ($erreur): ?>
    <div class="message message--erreur">⚠️ <?= e($erreur) ?></div>
  <?php endif; ?>

  <?php if ($succes): ?>
    <div class="message message--succes">
      ✅ Article envoyé pour publication ! Il sera visible sur
      <a href="https://lcv-amo.fr/blog/<?= e($succes) ?>.html" target="_blank" rel="noopener">le site</a>
      dans quelques minutes.
    </div>
  <?php endif; ?>

  <div class="panel">
    <form method="post" enctype="multipart/form-data" onsubmit="setTimeout(function(){ var b=document.getElementById('btn-publier'); b.disabled=true; b.textContent='Publication en cours…'; }, 0);">

      <label for="titre">Titre de l'article</label>
      <input type="text" id="titre" name="titre" required value="<?= e($valeurs['titre']) ?>">

      <label for="sous_titre">Sous-titre <span class="aide">(facultatif)</span></label>
      <input type="text" id="sous_titre" name="sous_titre" value="<?= e($valeurs['sous_titre']) ?>">

      <label for="vignette">Photo de vignette</label>
      <p class="aide" style="margin:-4px 0 6px;">Celle qui apparaît en haut de l'article et dans la liste du blog.</p>
      <input type="file" id="vignette" name="vignette" accept="image/jpeg,image/png,image/webp" required>

      <label>Photos dans l'article <span class="aide">(facultatif, jusqu'à 3 — placées automatiquement dans le texte)</span></label>
      <div class="photos-grid">
        <div><input type="file" name="photo1" accept="image/jpeg,image/png,image/webp"></div>
        <div><input type="file" name="photo2" accept="image/jpeg,image/png,image/webp"></div>
        <div><input type="file" name="photo3" accept="image/jpeg,image/png,image/webp"></div>
      </div>

      <label for="texte">Texte de l'article</label>
      <p class="aide" style="margin:-4px 0 6px;">Laisse une ligne vide entre deux paragraphes.</p>
      <textarea id="texte" name="texte" required><?= e($valeurs['texte']) ?></textarea>

      <div class="actions">
        <button type="button" id="btn-apercu" class="btn btn--ghost">👁️ Aperçu avant publication</button>
        <button type="submit" id="btn-publier" class="btn">Publier l'article</button>
      </div>
    </form>
  </div>

</div>

<dialog id="apercu">
  <div class="apercu-barre">
    <span>Aperçu — cet article n'est pas encore publié</span>
    <button type="button" id="btn-fermer-apercu">Fermer ✕</button>
  </div>
  <iframe id="apercu-iframe" title="Aperçu de l'article"></iframe>
</dialog>

<script>
(function () {
  "use strict";

  function echapper(texte) {
    var d = document.createElement("div");
    d.textContent = texte;
    return d.innerHTML;
  }

  // Même découpage en paragraphes que côté serveur (lib/texte.php : paragraphes()).
  function paragraphes(texte) {
    return texte.replace(/\r\n/g, "\n").trim().split(/\n\s*\n/).map(function (b) { return b.trim(); }).filter(Boolean);
  }

  // Même répartition des photos dans le texte que côté serveur (publier-article.php : inserer_photos()).
  function insererPhotos(blocs, urlsPhotos) {
    var n = blocs.length, k = urlsPhotos.length;
    if (k === 0 || n === 0) {
      return blocs.concat(urlsPhotos.map(function (u) { return { photo: u }; }));
    }
    var positions = [];
    for (var i = 1; i <= k; i++) {
      var pos = Math.round(n * i / (k + 1));
      pos = Math.max(1, Math.min(n, pos));
      while (positions.indexOf(pos) !== -1 && pos < n) pos++;
      positions.push(pos);
    }
    var resultat = [];
    blocs.forEach(function (bloc, index) {
      resultat.push({ texte: bloc });
      var numero = index + 1;
      var i2 = positions.indexOf(numero);
      if (i2 !== -1) resultat.push({ photo: urlsPhotos[i2] });
    });
    return resultat;
  }

  function paragrapheVersHtml(texte) {
    // Un seul retour à la ligne = un espace (comme marked par défaut), comme au moment de la publication réelle.
    return "<p>" + echapper(texte).replace(/\n+/g, " ") + "</p>";
  }

  function ouvrirApercu() {
    var titre = document.getElementById("titre").value.trim() || "Titre de l'article";
    var sousTitre = document.getElementById("sous_titre").value.trim();
    var texte = document.getElementById("texte").value.trim();
    var vignette = document.getElementById("vignette").files[0];
    var photos = ["photo1", "photo2", "photo3"]
      .map(function (nom) { return document.querySelector('[name="' + nom + '"]').files[0]; })
      .filter(Boolean);

    var urlVignette = vignette ? URL.createObjectURL(vignette) : "";
    var urlsPhotos = photos.map(function (f) { return URL.createObjectURL(f); });

    var blocs = texte ? insererPhotos(paragraphes(texte), urlsPhotos) : [];
    var corpsHtml = blocs.map(function (b) {
      return b.photo ? '<img src="' + b.photo + '" alt="">' : paragrapheVersHtml(b.texte);
    }).join("\n");

    var dateAujourdhui = new Date().toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" });

    var doc = '<!doctype html><html lang="fr"><head><meta charset="UTF-8">'
      + '<link rel="preconnect" href="https://fonts.googleapis.com">'
      + '<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
      + '<link rel="stylesheet" href="../assets/css/style.css">'
      + '<style>body{padding:40px 20px 80px;}</style></head><body>'
      + '<main id="main"><article><div class="container article-header">'
      + '<span class="eyebrow">Actualités</span>'
      + '<h1>' + echapper(titre) + '</h1>'
      + (sousTitre ? '<p class="lede">' + echapper(sousTitre) + '</p>' : '')
      + '<div class="article-meta"><span>Par Fanny Prieto</span><span>' + dateAujourdhui + '</span></div>'
      + (urlVignette ? '<div class="article-cover"><img src="' + urlVignette + '" alt=""></div>' : '')
      + '</div><div class="container"><div class="article-body">' + corpsHtml + '</div></div>'
      + '</article></main></body></html>';

    document.getElementById("apercu-iframe").srcdoc = doc;
    document.getElementById("apercu").showModal();
  }

  document.getElementById("btn-apercu").addEventListener("click", ouvrirApercu);
  document.getElementById("btn-fermer-apercu").addEventListener("click", function () {
    document.getElementById("apercu").close();
  });
})();
</script>
</body>
</html>
