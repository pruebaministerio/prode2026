<?php
/**
 * ================================================================
 *  BOT — PICKS KO (usuarios reales activos)
 *  Genera picks de eliminatorias para los usuarios reales activos.
 *  Rondas disponibles: R32, R16, QF, SF, FIN, 3RD, todos
 *
 *  Uso:
 *    http://localhost/prode2026/bot_picks_ko.php?ronda=todos
 *    http://localhost/prode2026/bot_picks_ko.php?ronda=R32
 * ================================================================
 */
define('BASE_URL',   'http://localhost/prode2026/api.php');
define('ADMIN_USER', 'huesoplu');
define('ADMIN_PASS', 'koke4812');
define('DELAY_MS',   30);

$RONDA_PARAM = strtoupper(trim($_GET['ronda'] ?? $argv[1] ?? 'todos'));

header('Content-Type: text/plain; charset=utf-8'); ob_implicit_flush();
function cc($c,$m){return "\033[{$c}m{$m}\033[0m";}
function lo($m){ echo cc('32','✓')." $m\n"; flush(); }
function le($m){ echo cc('31','✗')." $m\n"; flush(); }
function li($m){ echo cc('36','ℹ')." $m\n"; flush(); }
function lh($m){ echo "\n".cc('1;33',"=== $m ===")."\n"; flush(); }
function ls($m){ echo cc('33','⏭')." $m\n"; flush(); }

function api(array $p): array {
    $body = json_encode($p, JSON_UNESCAPED_UNICODE);
    $ch   = curl_init(BASE_URL);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    usleep(DELAY_MS * 1000);
    if ($err) return ['ok'=>false,'error'=>$err];
    return json_decode($raw,true) ?? ['ok'=>false,'raw'=>substr($raw,0,200)];
}

function sc_pick(string $t1, string $t2, array $pbt): array {
    $pool = array_merge($pbt[$t1]??[], $pbt[$t2]??[]);
    if (!$pool) return [];
    $n = mt_rand(0, min(3, count($pool)));
    if (!$n) return [];
    $ks = (array) array_rand($pool, $n);
    return array_map(fn($k) => $pool[$k], $ks);
}

function gen_score(): array {
    $r = mt_rand(0,9);
    if ($r < 4)     return [mt_rand(1,4), mt_rand(0,2)];
    elseif ($r < 7) return [mt_rand(0,2), mt_rand(1,3)];
    else            { $g = mt_rand(0,2); return [$g,$g]; }
}

// Usuarios reales
$REAL_USERS = [
    ['username'=>'rodrigues88', 'password'=>'prode2026!'],
    ['username'=>'gomezpaula',  'password'=>'prode2026!'],
    ['username'=>'fernandezmx', 'password'=>'prode2026!'],
    ['username'=>'lopezsofia',  'password'=>'prode2026!'],
    ['username'=>'martinezr99', 'password'=>'prode2026!'],
];

echo cc('1;33', str_repeat('═',56))."\n";
echo cc('1;33', "  BOT — PICKS KO: $RONDA_PARAM")."\n";
echo cc('1;33', str_repeat('═',56))."\n";

// ── Login admin ───────────────────────────────────────────────────
lh('Login');
$lr = api(['action'=>'login','username'=>ADMIN_USER,'password'=>ADMIN_PASS]);
if (empty($lr['ok'])) { le("Admin login falló."); exit(1); }
$adm = $lr['token'];
lo("Admin OK");

// ── Jugadores ─────────────────────────────────────────────────────
$pr  = api(['action'=>'get_players'])['players'] ?? [];
$pbt = [];
foreach ($pr as $team => $list) $pbt[$team] = array_column($list, 'name');
lo("Equipos con jugadores: ".count($pbt));

// ── Login usuarios reales activos + admin ─────────────────────────
lh('Usuarios activos');
$tokensUsers = [['label'=>'Admin (huesoplu)', 'token'=>$adm]]; // admin siempre

foreach ($REAL_USERS as $u) {
    $r = api(['action'=>'login','username'=>$u['username'],'password'=>$u['password']]);
    if (empty($r['ok']))               { le("@{$u['username']}: login falló — {$r['error']}"); continue; }
    if (empty($r['user']['active']))   { ls("@{$u['username']}: inactivo, saltando."); continue; }
    $tokensUsers[] = ['label'=>"@{$u['username']}", 'token'=>$r['token']];
    lo("@{$u['username']} OK");
}
li("Usuarios con picks: ".count($tokensUsers));

// ── Partidos KO disponibles ───────────────────────────────────────
$all_ko  = api(['action'=>'get_knockout_matches'])['knockout_matches'] ?? [];
$todas   = ['R32','R16','QF','SF','FIN','3RD'];
$rondas  = ($RONDA_PARAM === 'TODOS') ? $todas : [$RONDA_PARAM];

// ── Picks por ronda ───────────────────────────────────────────────
foreach ($rondas as $ronda) {
    $partidos = array_values(array_filter($all_ko, fn($k) => $k['ronda'] === $ronda && $k['team1'] && $k['team2']));
    if (!$partidos) { li("Ronda $ronda: sin partidos con equipos definidos, saltando."); continue; }

    lh("Picks $ronda (".count($partidos)." partidos)");

    foreach ($tokensUsers as $u) {
        $ok = 0; $skip = 0;
        foreach ($partidos as $ko) {
            [$s1,$s2] = gen_score();
            $payload = ['action'=>'save_knockout_pick','token'=>$u['token'],
                        'km_id'=>(int)$ko['id'],'s1'=>$s1,'s2'=>$s2,
                        'scorers'=>sc_pick($ko['team1'],$ko['team2'],$pbt)];
            if ($s1===$s2 && !empty($ko['team1']) && !empty($ko['team2'])) {
                $p1 = mt_rand(3, 7); $p2 = mt_rand(3, 7);
                if ($p1 === $p2) $p2++; // los penales nunca pueden terminar en empate
                $payload['pen1'] = $p1; $payload['pen2'] = $p2;
            }
            $r = api($payload);
            if (!empty($r['ok'])) $ok++;
            else $skip++;
        }
        lo(sprintf("  %-28s → %d picks%s", $u['label'], $ok,
            $skip ? cc('33'," ($skip err)"):''));
    }
}

echo "\n".cc('1;32','✅ FIN')."\n";
li("Próximo paso: bot3_resultados.php?ronda=R32");
