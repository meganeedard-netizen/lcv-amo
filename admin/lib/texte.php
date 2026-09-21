<?php
/** Petits utilitaires texte pour la publication d'articles. */

function slugifier($texte) {
    if (function_exists('transliterator_transliterate')) {
        $texte = transliterator_transliterate('Any-Latin; Latin-ASCII;', $texte) ?: $texte;
    } else {
        $correspondances = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'é' => 'e',
            'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ];
        $texte = strtr(mb_strtolower($texte), $correspondances);
    }
    $texte = strtolower($texte);
    $texte = preg_replace('/[^a-z0-9]+/', '-', $texte);
    return trim($texte, '-');
}

/** Découpe un texte libre (saisi dans un <textarea>) en paragraphes, sur les lignes vides. */
function paragraphes($texte) {
    $texte = str_replace("\r\n", "\n", trim($texte));
    $blocs = preg_split('/\n\s*\n/', $texte);
    return array_values(array_filter(array_map('trim', $blocs), fn($b) => $b !== ''));
}

/** Échappe une valeur pour l'insérer dans une chaîne YAML entre guillemets doubles. */
function yaml_valeur($texte) {
    $texte = str_replace(["\\", '"'], ["\\\\", '\\"'], $texte);
    return str_replace(["\r", "\n"], ' ', $texte);
}

/**
 * Sépare le corps Markdown d'un article en texte "libre" et liste des photos
 * qui y avaient été insérées automatiquement (inverse de inserer_photos()).
 */
function extraire_texte_et_photos($corpsMarkdown) {
    $texte = [];
    $photos = [];
    foreach (paragraphes($corpsMarkdown) as $bloc) {
        if (preg_match('/^!\[[^\]]*\]\(([^)]+)\)$/', $bloc, $m)) {
            $photos[] = $m[1];
        } elseif (preg_match('/^##\s+(.+)$/', $bloc, $m)) {
            // Titre détecté automatiquement à la publication : on retire le "## ".
            $texte[] = $m[1];
        } elseif (preg_match('/^-\s+/', $bloc)) {
            // Liste détectée automatiquement à la publication : on retire le "- " de chaque ligne.
            $lignes = array_map(fn($l) => preg_replace('/^-\s+/', '', $l), explode("\n", $bloc));
            $texte[] = implode("\n", $lignes);
        } else {
            $texte[] = $bloc;
        }
    }
    return ['texte' => implode("\n\n", $texte), 'photos' => $photos];
}

/** Résumé court (pour la balise meta description et la vignette de la liste du blog). */
function resume_court($texte, $longueurMax = 160) {
    $texte = trim(preg_replace('/\s+/', ' ', $texte));
    if (mb_strlen($texte) <= $longueurMax) {
        return $texte;
    }
    return rtrim(mb_substr($texte, 0, $longueurMax)) . '…';
}
