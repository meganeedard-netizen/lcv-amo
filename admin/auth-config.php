<?php
/**
 * Mot de passe du tableau de bord (/admin/tableau-de-bord.php).
 *
 * Pour changer le mot de passe : générer un nouveau hash bcrypt, par exemple
 * en ligne de commande sur un poste avec PHP :
 *   php -r "echo password_hash('nouveau-mot-de-passe', PASSWORD_BCRYPT);"
 * puis remplacer la valeur ci-dessous.
 */

define('ADMIN_PASSWORD_HASH', '$2b$12$cTMrPWrTrvWSfyqMkzlHx.T2pjXAwLPnCJ5gjMMCJ9.YnQID0rxKi');
