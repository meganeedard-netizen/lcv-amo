<?php
/**
 * Modèle de configuration SMTP. Copier en mail-config.php (ignoré par git)
 * et renseigner le vrai mot de passe. En production, ce fichier est généré
 * automatiquement par le workflow de déploiement à partir des secrets GitHub
 * SMTP_USER et SMTP_PASSWORD.
 */

return [
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_user' => 'contact@lcv-amo.fr',
    'smtp_pass' => 'CHANGE_ME',
];
