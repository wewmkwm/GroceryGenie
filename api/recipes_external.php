<?php
// api/recipes_external.php
// Lightweight proxy to TheMealDB (no API key required)
// Usage examples:
//   ?action=categories
//   ?action=search&q=pasta
//   ?action=filter&c=Seafood
//   ?action=details&id=52772

header('Content-Type: application/json; charset=utf-8');

// Build upstream URL
$base = 'https://www.themealdb.com/api/json/v1/1/';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$url = '';

switch ($action) {
  case 'categories':
    $url = $base . 'list.php?c=list';
    break;
  case 'areas':
    $url = $base . 'list.php?a=list';
    break;
  case 'ingredients':
    $url = $base . 'list.php?i=list';
    break;
  case 'search':
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $url = $base . 'search.php?s=' . urlencode($q);
    break;
  case 'filter':
    // filter by category (c) or area (a) or ingredient (i)
    if (isset($_GET['c'])) $url = $base . 'filter.php?c=' . urlencode($_GET['c']);
    elseif (isset($_GET['a'])) $url = $base . 'filter.php?a=' . urlencode($_GET['a']);
    elseif (isset($_GET['i'])) $url = $base . 'filter.php?i=' . urlencode($_GET['i']);
    break;
  case 'details':
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';
    $url = $base . 'lookup.php?i=' . urlencode($id);
    break;
}

if (!$url) {
  echo json_encode(['ok' => false, 'message' => 'Invalid action']);
  exit;
}

// Fetch via cURL with sane timeouts
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_TIMEOUT => 15,
  CURLOPT_CONNECTTIMEOUT => 8,
  CURLOPT_SSL_VERIFYPEER => true,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $code >= 400) {
  echo json_encode(['ok' => false, 'message' => 'Upstream error', 'code' => $code, 'error' => $err]);
  exit;
}

// Pass-through JSON
echo $resp;
exit;

