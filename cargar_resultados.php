<?php
/**
 * ================================================================
 *  PICKS ELIMINATORIOS — Prode Vale 4 · Mundial USA 2026
 * ================================================================
 *  Carga picks aleatorios del R32 para TODOS los usuarios.
 *  Correr DESPUÉS de que el R32 esté generado.
 *
 *  Uso web: http://localhost/prode2026/cargar_ko_picks.php
 * ================================================================
 */

define('BASE_URL',  'http://localhost/prode2026/api.php');
define('ADMIN_USER','huesoplu');
define('ADMIN_PASS','koke4812');
define('DELAY_MS',  40);
define('BOT_PASS',  'BotPass123!');   // contraseña de los usuarios bot

// ── Output ────────────────────────────────────────────────────────
$isCli = php_sapi_name() === 'cli';
if (!$isCli) { header('Content-Type: text/plain; charset=utf-8'); ob_implicit_flush(); }

function cc(string $c, string $m): string { global $isCli; return $isCli ? "\033[{$c}m{$m}\033[0m" : $m; }
function log_ok(string $m)  : void { echo cc('32','✓') . " $m\n"; flush(); }
function log_err(string $m) : void { echo cc('31','✗') . " $m\n"; flush(); }
function log_info(string $m): void { echo cc('36','ℹ') . " $m\n"; flush(); }
function log_head(string $m): void { echo "\n" . cc('1;33',"=== $m ===") . "\n"; flush(); }

// ── HTTP ──────────────────────────────────────────────────────────
function api(array $payload): array {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch   = curl_init(BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw = curl_exec($ch); curl_close($ch);
    usleep(DELAY_MS * 1000);
    return json_decode($raw, true) ?? ['ok'=>false,'raw'=>substr($raw,0,300)];
}

function gen_score(): array {
    $r = mt_rand(0,9);
    if ($r < 4)     return [mt_rand(1,4), mt_rand(0,2)];
    elseif ($r < 7) return [mt_rand(0,2), mt_rand(1,3)];
    else            { $g = mt_rand(0,2); return [$g, $g]; }
}

// ══════════════════════════════════════════════════════════════════
echo cc('1;33', str_repeat('═', 54)) . "\n";
echo cc('1;33', "  KO PICKS — Prode Vale 4 · R32 y siguientes") . "\n";
echo cc('1;33', str_repeat('═', 54)) . "\n";

// ── 1. Login admin y obtener lista de usuarios ────────────────────
log_head('Logueando admin');
$resp = api(['action'=>'login','username'=>ADMIN_USER,'password'=>ADMIN_PASS]);
if (empty($resp['ok'])) { log_err("Login admin fallido: " . ($resp['error']??json_encode($resp))); exit(1); }
$adminToken = $resp['token'];
log_ok("Admin OK");

log_head('Obteniendo lista de usuarios');
$resp = api(['action'=>'get_users','token'=>$adminToken]);
if (empty($resp['ok'])) { log_err("get_users fallido: " . ($resp['error']??json_encode($resp))); exit(1); }
$allUsers = $resp['users'] ?? [];
log_ok("Usuarios encontrados: " . count($allUsers));

// ── 2. Obtener partidos eliminatorios ─────────────────────────────
log_head('Obteniendo partidos KO');
$resp    = api(['action'=>'get_knockout_matches']);
$koMs    = $resp['knockout_matches'] ?? [];
if (empty($koMs)) { log_err("No hay partidos KO generados todavía."); exit(1); }
log_ok("Partidos KO encontrados: " . count($koMs));

// Agrupar por ronda
$byRonda = [];
foreach ($koMs as $ko) $byRonda[$ko['ronda']][] = $ko;

// ── 3. Para cada usuario: login → picks ──────────────────────────
log_head('Cargando picks KO por usuario');

$RONDAS = ['R32','R16','QF','SF','FIN','3RD'];
$ok_users = 0;

foreach ($allUsers as $u) {
    $username = $u['username'];
    $nombre   = "{$u['nombre']} {$u['apellido']}";

    // Determinar contraseña
    $pass = (strpos($username, 'bot_') === 0) ? BOT_PASS : null;

    if ($pass === null) {
        log_info("  $nombre ($username) → contraseña desconocida, saltando.");
        continue;
    }

    // Login
    $lr = api(['action'=>'login','username'=>$username,'password'=>$pass]);
    if (empty($lr['ok'])) {
        log_err("  Login fallido para $username: " . ($lr['error']??''));
        continue;
    }
    $tok = $lr['token'];

    // Picks por ronda disponible
    $ok_c = 0; $total_c = 0;
    foreach ($RONDAS as $ronda) {
        $partidos = $byRonda[$ronda] ?? [];
        foreach ($partidos as $ko) {
            [$s1, $s2] = gen_score();
            $resp = api([
                'action'  => 'save_knockout_pick',
                'token'   => $tok,
                'km_id'   => (int)$ko['id'],
                's1'      => $s1,
                's2'      => $s2,
                'scorers' => [],
            ]);
            $total_c++;
            if (!empty($resp['ok'])) $ok_c++;
        }
    }

    log_ok(sprintf("  %-30s → %d/%d picks KO", $nombre, $ok_c, $total_c));
    $ok_users++;
}

// ── Resumen ───────────────────────────────────────────────────────
log_head('LISTO');
log_ok("Usuarios procesados: $ok_users / " . count($allUsers));
log_info("Ahora podés ver la Tabla con puntajes en la app.");
echo "\n";