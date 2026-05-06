<?php
require_once 'config.php';
header('Content-Type: application/json');

$doc_id = $_GET['id'] ?? 0;
if (!$doc_id) die(json_encode([]));

$stmt = $pdo->prepare("SELECT f.* FROM signature_fields f JOIN signers s ON f.signer_id = s.id WHERE s.document_id = ? AND f.status = 'filled'");
$stmt->execute([$doc_id]);
$fields = $stmt->fetchAll();

echo json_encode($fields);
?>
