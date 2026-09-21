<?php
/**
 * Modèle de configuration du jeton GitHub technique utilisé par
 * publier-article.php pour publier les articles de Fanny sans passer par
 * GitHub. Copier en cms-token.php (ignoré par git) et renseigner le vrai
 * jeton pour tester en local. En production, ce fichier est généré
 * automatiquement par le workflow de déploiement à partir du secret GitHub
 * CMS_GITHUB_TOKEN (voir DEPLOIEMENT.md).
 */

return [
    'token' => 'CHANGE_ME',
];
