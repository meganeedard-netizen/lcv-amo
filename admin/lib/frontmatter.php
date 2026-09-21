<?php
/**
 * Lecture/écriture du frontmatter YAML des articles (content/blog, content/drafts).
 * Format volontairement simple (clé: "valeur" ou clé: valeur pour la date) car
 * on est les seuls à l'écrire — pas besoin d'un vrai parseur YAML générique.
 */

/** Découpe un fichier Markdown en [données du frontmatter, corps de l'article]. */
function frontmatter_parser($texte) {
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $texte, $m)) {
        return ['data' => [], 'body' => $texte];
    }
    $data = [];
    foreach (explode("\n", $m[1]) as $ligne) {
        if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $ligne, $mm)) {
            $valeur = trim($mm[2]);
            if (strlen($valeur) >= 2 && $valeur[0] === '"' && substr($valeur, -1) === '"') {
                $valeur = substr($valeur, 1, -1);
                $valeur = str_replace('\\"', '"', $valeur);
                $valeur = str_replace('\\\\', '\\', $valeur);
            }
            $data[$mm[1]] = $valeur;
        }
    }
    return ['data' => $data, 'body' => trim($m[2])];
}

/** Reconstruit un fichier Markdown à partir des données (ordonnées) du frontmatter et du corps. */
function frontmatter_ecrire($data, $corps) {
    $texte = "---\n";
    foreach ($data as $cle => $valeur) {
        if ($valeur === null || $valeur === '') {
            continue;
        }
        if ($cle === 'date') {
            $texte .= "date: $valeur\n";
        } else {
            $texte .= "$cle: \"" . yaml_valeur($valeur) . "\"\n";
        }
    }
    $texte .= "---\n\n" . trim($corps) . "\n";
    return $texte;
}
