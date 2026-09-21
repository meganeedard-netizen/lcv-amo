<?php
/**
 * Redimensionne et compresse une photo uploadée en JPEG, pour rester sous la
 * limite de l'API GitHub (fichiers < 1 Mo) et garder le site rapide.
 */

class ImageTraitementException extends Exception {}

function image_vers_jpeg_optimise($cheminTmp, $largeurMax = 1600, $tailleMaxOctets = 900000) {
    if (!extension_loaded('gd')) {
        throw new ImageTraitementException("L'extension GD n'est pas disponible sur le serveur.");
    }

    $info = @getimagesize($cheminTmp);
    if ($info === false) {
        throw new ImageTraitementException("Fichier image invalide ou illisible.");
    }

    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($cheminTmp);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($cheminTmp);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($cheminTmp);
            break;
        default:
            throw new ImageTraitementException("Format d'image non supporté (JPEG, PNG ou WEBP uniquement).");
    }
    if (!$source) {
        throw new ImageTraitementException("Impossible de lire cette image.");
    }

    $largeur = imagesx($source);
    $hauteur = imagesy($source);

    if ($largeur > $largeurMax) {
        $nouvelleHauteur = (int) round($hauteur * ($largeurMax / $largeur));
        $redim = imagecreatetruecolor($largeurMax, $nouvelleHauteur);
        // fond blanc pour les PNG avec transparence (une photo de blog n'a pas besoin d'alpha)
        imagefill($redim, 0, 0, imagecolorallocate($redim, 255, 255, 255));
        imagecopyresampled($redim, $source, 0, 0, 0, 0, $largeurMax, $nouvelleHauteur, $largeur, $hauteur);
        imagedestroy($source);
        $source = $redim;
    }

    $qualite = 85;
    do {
        ob_start();
        imagejpeg($source, null, $qualite);
        $donnees = ob_get_clean();
        $qualite -= 10;
    } while (strlen($donnees) > $tailleMaxOctets && $qualite >= 40);

    imagedestroy($source);

    return $donnees;
}
