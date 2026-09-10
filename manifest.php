<?php
// Dynamic web-app manifest.
//
// The static manifest.json hard-codes start_url = index.php, which is right for
// parents but wrong for a child's device: once the child adds the app to their
// home screen, the icon must reopen THEIR token URL, not the marketing page.
// child.php / child_books.php / reading_timer.php link here with ?token=... so
// each child's install gets its own start_url and its own app identity.
require_once __DIR__ . '/functions.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$token = $_GET['token'] ?? '';
$child = $token !== '' ? get_child_by_token($token) : null;

$manifest = [
    'name'             => 'Ajaraamat',
    'short_name'       => 'Ajaraamat',
    'description'      => 'Laste lugemis- ja ekraaniaja jälgija, mis aitab hoida tasakaalu.',
    'lang'            => 'et',
    'icons'            => [
        ['src' => 'icon-192.png?v=2', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => 'icon-512.png?v=2', 'sizes' => '512x512', 'type' => 'image/png'],
    ],
    'theme_color'      => '#8B5CF6',
    'background_color' => '#FFE8D6',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'scope'           => './',
    'start_url'        => 'index.php',
];

if ($child) {
    $t = rawurlencode($token);
    $manifest['start_url']  = 'child.php?token=' . $t;
    $manifest['id']         = 'child-' . $t;               // distinct install per child
    $manifest['name']       = $child['name'] . ' · Ajaraamat';
    $manifest['short_name'] = $child['name'];
    $manifest['shortcuts']  = [
        ['name' => 'Alusta lugemist', 'short_name' => 'Taimer', 'url' => 'reading_timer.php?token=' . $t],
        ['name' => 'Raamatud', 'short_name' => 'Raamatud', 'url' => 'child_books.php?token=' . $t],
    ];
}

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
