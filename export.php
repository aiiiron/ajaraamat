<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$childId = (int) ($_GET['child'] ?? 0);
if (!child_belongs_to_family($childId, $familyId)) {
    header('Location: children.php');
    exit;
}
$child = get_child($childId);

$pdo = get_db();
$stmt = $pdo->prepare("SELECT entry_date, raamat, raamat_comment, ekraan, ekraan_comment FROM entries WHERE child_id = :cid ORDER BY entry_date ASC, id ASC");
$stmt->execute([':cid' => $childId]);
$entries = $stmt->fetchAll();

$filenameSafe = preg_replace('/[^a-z0-9]+/i', '-', strtolower($child['name']));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ajaraamat-' . $filenameSafe . '-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Kuupäev', 'Raamat (min)', 'Raamatu kommentaar', 'Ekraan (min)', 'Ekraani kommentaar']);
foreach ($entries as $e) {
    fputcsv($out, [$e['entry_date'], $e['raamat'], $e['raamat_comment'], $e['ekraan'], $e['ekraan_comment']]);
}
fclose($out);
exit;
