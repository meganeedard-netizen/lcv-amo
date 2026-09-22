<?php
/**
 * Traitement du formulaire de contact, La Clef de Voûte
 * Envoie un email à contact@lcv-amo.fr via SMTP authentifié (PHPMailer),
 * plus fiable sur mutualisé Hostinger que la fonction mail() native.
 */

require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json; charset=utf-8');

$destinataire = 'contact@lcv-amo.fr';

$mailConfig = @include __DIR__ . '/mail-config.php';
if (!is_array($mailConfig)) {
    http_response_code(500);
    error_log('lcv-amo: mail-config.php manquant ou invalide');
    echo json_encode(['ok' => false, 'message' => "Erreur de configuration de l'envoi."]);
    exit;
}

function envoyerEmail(array $mailConfig, $destinataire, $sujet, $corps, $replyToNom = null, $replyToEmail = null, $fromEmail = null, $fromNom = 'La Clef de Voûte') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['smtp_user'];
        $mail->Password   = $mailConfig['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $mailConfig['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromEmail ?: $mailConfig['smtp_user'], $fromNom);
        $mail->addAddress($destinataire);
        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToNom ?: $replyToEmail);
        }

        $mail->Subject = $sujet;
        $mail->Body    = $corps;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('lcv-amo: échec envoi email SMTP - ' . $mail->ErrorInfo);
        return false;
    }
}

function repondre($ok, $message) {
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

function enregistrerDemande($entree) {
    $dataDir = __DIR__ . '/data';
    $file = $dataDir . '/contacts.json';

    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    if (!file_exists($dataDir . '/.htaccess')) {
        file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
    }

    $fp = fopen($file, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        error_log('lcv-amo: impossible d\'enregistrer la demande dans data/contacts.json');
        return;
    }

    $contenu = stream_get_contents($fp);
    $data = json_decode($contenu, true);
    if (!is_array($data)) {
        $data = [];
    }

    array_unshift($data, $entree);
    $data = array_slice($data, 0, 500); // garde les 500 demandes les plus récentes

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    repondre(false, 'Méthode non autorisée.');
}

// Piège à robots : si rempli, on répond succès sans envoyer
if (!empty($_POST['site_web'])) {
    repondre(true, 'Merci.');
}

$nom       = trim($_POST['nom'] ?? '');
$structure = trim($_POST['structure'] ?? '');
$email     = trim($_POST['email'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$typeProjet = trim($_POST['type_projet'] ?? '');
$message   = trim($_POST['message'] ?? '');

if ($nom === '' || $email === '' || $message === '') {
    http_response_code(422);
    repondre(false, 'Merci de renseigner les champs obligatoires.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    repondre(false, 'Adresse email invalide.');
}

$sujet = 'Nouveau message depuis lcv-amo.fr : ' . $nom;

$corps = "Nouvelle demande de contact reçue sur lcv-amo.fr\n\n";
$corps .= "Nom : {$nom}\n";
$corps .= "Commune / structure : {$structure}\n";
$corps .= "Email : {$email}\n";
$corps .= "Téléphone : {$telephone}\n";
$corps .= "Type de projet : {$typeProjet}\n\n";
$corps .= "Message :\n{$message}\n";

// Expéditeur distinct du destinataire pour éviter le classement en spam
// (un mail envoyé de contact@lcv-amo.fr vers lui-même est souvent filtré).
$envoye = envoyerEmail($mailConfig, $destinataire, $sujet, $corps, $nom, $email, 'no-reply@lcv-amo.fr', 'Formulaire de contact — lcv-amo.fr');

if ($envoye) {
    // Email de confirmation envoyé au visiteur
    $sujetConfirmation = 'Votre demande a bien été reçue, La Clef de Voûte';

    $corpsConfirmation = "Bonjour {$nom},\n\n";
    $corpsConfirmation .= "Votre demande a bien été reçue. Fanny Prieto vous recontactera dans les plus brefs délais pour échanger sur votre projet.\n\n";
    $corpsConfirmation .= "Récapitulatif de votre message :\n{$message}\n\n";
    $corpsConfirmation .= "À très bientôt,\n";
    $corpsConfirmation .= "La Clef de Voûte, Assistance à Maîtrise d'Ouvrage\n";

    envoyerEmail($mailConfig, $email, $sujetConfirmation, $corpsConfirmation, 'La Clef de Voûte', $destinataire, 'no-reply@lcv-amo.fr', 'La Clef de Voûte');

    enregistrerDemande([
        'date'        => date('c'),
        'nom'         => $nom,
        'structure'   => $structure,
        'email'       => $email,
        'telephone'   => $telephone,
        'type_projet' => $typeProjet,
        'message'     => $message
    ]);

    repondre(true, 'Message envoyé.');
} else {
    http_response_code(500);
    repondre(false, "Erreur lors de l'envoi.");
}
