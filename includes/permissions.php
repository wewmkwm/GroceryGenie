<?php
// includes/permissions.php - simple RBAC helpers
if (session_status() === PHP_SESSION_NONE) session_start();

$ROLE_PERMS = [
  'admin' => [
    'manage_storeowners','manage_recipes','manage_users','view_reports','impersonate'
  ],
  'store_owner' => [
    'manage_inventory','view_orders','fulfill_orders','view_reports'
  ],
  'customer' => [
    'place_orders','create_recipe','chat','view_orders'
  ],
];

function set_role(string $role): void { $_SESSION['role'] = $role; }
function role(): string { return $_SESSION['role'] ?? ''; }
function has_role(string $role): bool { return role() === $role; }
function can(string $perm): bool {
  global $ROLE_PERMS; $r = role();
  return in_array($perm, $ROLE_PERMS[$r] ?? [], true);
}
function require_role(string $role): void {
  if (!has_role($role)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}
function require_can(string $perm): void {
  if (!can($perm)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

