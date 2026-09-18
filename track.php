<?php
/**
 * Compteur de visites anonyme, La Clef de Voûte.
 * Aucune donnée personnelle ni cookie : juste un compteur par jour et par page.
 */

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/data';
$file = $dataDir . '/visits.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!file_exists($dataDir . '/.htaccess')) {
    file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor/i', $ua)) {
    http_response_code(204);
    exit;
}

$page = $_POST['page'] ?? '/';
$page = preg_replace('/[^a-zA-Z0-9\/_\-\.]/', '', $page);
$page = substr($page, 0, 200);
if ($page === '') {
    $page = '/';
}

$today = date('Y-m-d');

$fp = fopen($file, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $content = stream_get_contents($fp);
    $data = json_decode($content, true);
    if (!is_array($data)) {
        $data = ['total' => 0, 'by_day' => [], 'by_page' => []];
    }

    $data['total'] = ($data['total'] ?? 0) + 1;
    $data['by_day'][$today] = ($data['by_day'][$today] ?? 0) + 1;
    $data['by_page'][$page] = ($data['by_page'][$page] ?? 0) + 1;

    // Garde seulement les 180 derniers jours pour éviter que le fichier grossisse indéfiniment
    if (count($data['by_day']) > 180) {
        uksort($data['by_day'], 'strcmp');
        $data['by_day'] = array_slice($data['by_day'], -180, null, true);
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

http_response_code(204);
