<?php
declare(strict_types=1);

const SUPABASE_URL = 'https://cymdnshkisftyulbfncf.supabase.co';
const SUPABASE_ANON_KEY = 'sb_publishable_Q4lVE-CTOYafQL3D2J44zw_sAOBJ7H9';
const SITE_URL = 'https://grouptreff.de/';

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function short_group_id(string $id): string {
    $hash = 0;

    foreach (str_split($id) as $char) {
        $hash = ((($hash << 5) - $hash) + ord($char)) & 0xFFFFFFFF;
        if ($hash >= 0x80000000) {
            $hash -= 0x100000000;
        }
    }

    return (string) (10000 + (abs($hash) % 90000));
}

function is_social_bot(string $ua): bool {
    return (bool) preg_match(
        '/facebookexternalhit|Facebot|Twitterbot|WhatsApp|TelegramBot|LinkedInBot|Discordbot|Slackbot|Pinterest/i',
        $ua
    );
}

$requested = trim((string)($_GET['g'] ?? ''));

if ($requested === '') {
    http_response_code(404);
    exit('Grupo no encontrado');
}

$requestedUpper = strtoupper($requested);

$endpoint = SUPABASE_URL .
    '/rest/v1/groups?select=id,name,description,image_url,platform,category,country,status&status=eq.approved';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $status < 200 || $status >= 300) {
    http_response_code(502);
    exit('No se pudo cargar el grupo');
}

$groups = json_decode($response, true);

if (!is_array($groups)) {
    http_response_code(500);
    exit('Respuesta inválida');
}

$group = null;

foreach ($groups as $item) {
    if (!is_array($item) || empty($item['id'])) {
        continue;
    }

    $fullId = (string)$item['id'];
    $shortId = short_group_id($fullId);

    if ($fullId === $requested || strtoupper($shortId) === $requestedUpper) {
        $group = $item;
        break;
    }
}

if (!$group) {
    http_response_code(404);
    exit('Grupo no encontrado');
}

$flags = [
    'DE' => '🇩🇪',
    'US' => '🇺🇸',
    'ES' => '🇪🇸',
    'AU' => '🇦🇺',
    'GB' => '🇬🇧',
];

$countries = [
    'DE' => 'Deutschland',
    'US' => 'USA',
    'ES' => 'Spanien',
    'AU' => 'Australien',
    'GB' => 'England',
];

$country = strtoupper(trim((string)($group['country'] ?? 'DE')));
$flag = $flags[$country] ?? '🌍';
$countryName = $countries[$country] ?? $country;

$name = trim((string)($group['name'] ?? 'WhatsApp Gruppe'));
$description = trim((string)($group['description'] ?? ''));

if ($description === '') {
    $description = 'Entdecke diese Gruppe auf GroupTreff.';
}

$image = trim((string)($group['image_url'] ?? ''));

if ($image === '') {
    $image = SITE_URL . 'share-preview.png';
}

$shortId = short_group_id((string)$group['id']);
$isVotePage = (string) ($_GET['vote'] ?? '') === '1';
$query = '?g=' . rawurlencode($shortId) . ($isVotePage ? '&vote=1' : '');
$canonical = SITE_URL . 'group.php' . $query;
$destination = SITE_URL . $query;

$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

if (!is_social_bot($ua)) {
    header('Location: ' . $destination, true, 302);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');

?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($name) ?> | GroupTreff</title>
<meta name="description" content="<?= e($description) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="GroupTreff">
<meta property="og:title" content="<?= e($flag . ' ' . $name) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<meta property="og:image:secure_url" content="<?= e($image) ?>">
<meta property="og:image:alt" content="<?= e($name) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($flag . ' ' . $name) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image" content="<?= e($image) ?>">

<link rel="canonical" href="<?= e($canonical) ?>">
</head>
<body>
<p><?= e($flag . ' ' . $name . ' – ' . $countryName) ?></p>
<p><a href="<?= e($destination) ?>">Gruppe auf GroupTreff öffnen</a></p>
</body>
</html>
