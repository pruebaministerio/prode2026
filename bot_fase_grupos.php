<?php
/**
 * ================================================================
 *  BOT — FASE DE GRUPOS COMPLETA
 *  1) Picks de los 5 usuarios reales ya registrados
 *  2) Picks del admin (opcional)
 *  3) Resultados de los 72 partidos (solo admin)
 *
 *  Uso:
 *    ?paso=picks    → solo genera picks de los 5 usuarios
 *    ?paso=results  → solo carga los 72 resultados (requiere admin logueado)
 *    ?paso=todo     → picks + resultados en un solo paso
 *
 *  Parámetros opcionales:
 *    &admin_user=xxx  &admin_pass=yyy  (si querés picks del admin también)
 *
 *  Ej: http://localhost/prode2026/bot_fase_grupos.php?paso=todo&admin_user=huesoplu&admin_pass=koke4812
 * ================================================================
 */
define('BASE_URL', 'http://localhost/prode2026/api.php');
define('DELAY_MS', 30);

$PASO        = strtolower(trim($_GET['paso'] ?? 'todo'));
$ADMIN_USER  = trim($_GET['admin_user'] ?? '');
$ADMIN_PASS  = trim($_GET['admin_pass'] ?? '');

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

// ── Marcador aleatorio grupos ─────────────────────────────────────
function gen_score(): array {
    $r = mt_rand(0,9);
    if ($r < 4)     return [mt_rand(1,4), mt_rand(0,2)];
    elseif ($r < 7) return [mt_rand(0,2), mt_rand(1,3)];
    else            { $g = mt_rand(0,2); return [$g, $g]; }
}

// ── Goleadores para un pronóstico (máx 3, mezclados) ─────────────
function sc_pick(string $t1, string $t2, array $pbt): array {
    $pool = array_merge($pbt[$t1]??[], $pbt[$t2]??[]);
    if (!$pool) return [];
    $n  = mt_rand(0, min(3, count($pool)));
    if (!$n) return [];
    $ks = (array) array_rand($pool, $n);
    return array_map(fn($k) => $pool[$k], $ks);
}

// ── Goleadores para un resultado (por equipo, según goles) ────────
function sc_res(string $team, int $goals, array $pbt): array {
    $pl = $pbt[$team] ?? [];
    if (!$pl || $goals <= 0) return [];
    $n  = min($goals, count($pl));
    $ks = (array) array_rand($pl, $n);
    return array_map(fn($k) => $pl[$k], $ks);
}

// ══════════════════════════════════════════════════════════════════
echo cc('1;33', str_repeat('═',58))."\n";
echo cc('1;33', "  BOT — FASE DE GRUPOS  [paso=$PASO]")."\n";
echo cc('1;33', str_repeat('═',58))."\n";

// ── 5 Usuarios reales de bot_register_users ───────────────────────
$REAL_USERS = [
    ['username'=>'rodrigues88', 'password'=>'prode2026!', 'nombre'=>'Lucas',       'apellido'=>'Rodríguez'],
    ['username'=>'gomezpaula',  'password'=>'prode2026!', 'nombre'=>'Paula',        'apellido'=>'Gómez'],
    ['username'=>'fernandezmx', 'password'=>'prode2026!', 'nombre'=>'Maximiliano',  'apellido'=>'Fernández'],
    ['username'=>'lopezsofia',  'password'=>'prode2026!', 'nombre'=>'Sofía',        'apellido'=>'López'],
    ['username'=>'martinezr99', 'password'=>'prode2026!', 'nombre'=>'Ramiro',       'apellido'=>'Martínez'],
];

// ── Partidos y jugadores ──────────────────────────────────────────
$matches = api(['action'=>'get_matches'])['matches'] ?? [];
if (!$matches) { le("Sin partidos en DB."); exit(1); }

$pr  = api(['action'=>'get_players'])['players'] ?? [];
$pbt = [];
foreach ($pr as $team => $list) $pbt[$team] = array_column($list, 'name');
li("Partidos: ".count($matches)."  |  Equipos con jugadores: ".count($pbt));

// ══════════════════════════════════════════════════════════════════
// PASO 1: PICKS
// ══════════════════════════════════════════════════════════════════
if (in_array($PASO, ['picks','todo'])) {
    lh('PICKS — Usuarios reales');

    $usersConToken = [];

    // Login de cada usuario real
    foreach ($REAL_USERS as $u) {
        $lr = api(['action'=>'login','username'=>$u['username'],'password'=>$u['password']]);
        if (empty($lr['ok'])) {
            le("{$u['nombre']} {$u['apellido']} (@{$u['username']}): login falló — {$lr['error']}");
            continue;
        }
        if (empty($lr['user']['active'])) {
            ls("{$u['nombre']} {$u['apellido']} (@{$u['username']}): cuenta inactiva, saltando.");
            continue;
        }
        $usersConToken[] = ['data'=>$u, 'token'=>$lr['token']];
        lo("Login OK → @{$u['username']}");
    }

    // Login admin para picks (opcional)
    if ($ADMIN_USER && $ADMIN_PASS) {
        $lr = api(['action'=>'login','username'=>$ADMIN_USER,'password'=>$ADMIN_PASS]);
        if (!empty($lr['ok'])) {
            $usersConToken[] = ['data'=>['nombre'=>'Admin','apellido'=>'','username'=>$ADMIN_USER], 'token'=>$lr['token']];
            lo("Login OK → @$ADMIN_USER (admin)");
        } else {
            le("Admin login falló: ".($lr['error']??''));
        }
    }

    if (!$usersConToken) { le("Ningún usuario activo disponible."); if($PASO==='picks') exit(1); }

    lh('Generando picks de grupos');
    foreach ($usersConToken as $u) {
        $ok = 0; $skip = 0;
        foreach ($matches as $m) {
            [$s1,$s2] = gen_score();
            $r = api(['action'=>'save_pick','token'=>$u['token'],
                      'match_id'=>(int)$m['id'],'s1'=>$s1,'s2'=>$s2,
                      'scorers'=>sc_pick($m['team1'],$m['team2'],$pbt)]);
            if (!empty($r['ok'])) $ok++;
            else $skip++;
        }
        $lbl = "{$u['data']['nombre']} {$u['data']['apellido']} (@{$u['data']['username']})";
        lo(sprintf("%-40s → %d picks OK  %s", $lbl, $ok,
            $skip ? cc('33',"($skip errores)") : ''));
    }
}

// ══════════════════════════════════════════════════════════════════
// PASO 2: RESULTADOS
// ══════════════════════════════════════════════════════════════════
if (in_array($PASO, ['results','todo'])) {
    lh('RESULTADOS — Login admin');

    if (!$ADMIN_USER || !$ADMIN_PASS) {
        le("Faltan credenciales de admin. Pasalas como: &admin_user=xxx&admin_pass=yyy");
        exit(1);
    }

    $lr = api(['action'=>'login','username'=>$ADMIN_USER,'password'=>$ADMIN_PASS]);
    if (empty($lr['ok'])) { le("Admin login falló: ".($lr['error']??'')); exit(1); }
    $adm = $lr['token'];
    lo("Admin OK (@$ADMIN_USER)");

    $results = api(['action'=>'get_results'])['results'] ?? [];

    lh('Cargando 72 resultados de grupos');
    $auto = 0; $skip = 0;
    foreach ($matches as $m) {
        $mid = (int)$m['id'];
        if (isset($results[$mid]) && $results[$mid]['s1'] !== null) {
            ls("#{$mid} {$m['team1']} vs {$m['team2']} — ya tiene resultado, respetando.");
            $skip++; continue;
        }
        [$s1,$s2] = gen_score();
        $sc1 = sc_res($m['team1'], $s1, $pbt);
        $sc2 = sc_res($m['team2'], $s2, $pbt);
        $r = api(['action'=>'set_result','token'=>$adm,
                  'match_id'=>$mid,'s1'=>$s1,'s2'=>$s2,
                  'scorers1'=>$sc1,'scorers2'=>$sc2]);
        if (!empty($r['ok'])) {
            $auto++;
            $g1 = $sc1 ? implode(', ',$sc1) : '—';
            $g2 = $sc2 ? implode(', ',$sc2) : '—';
            lo(sprintf("Grp %s  %s %d–%d %s  ⚽[%s | %s]",
                $m['grp'], $m['team1'], $s1, $s2, $m['team2'], $g1, $g2));
        } else {
            le("#{$mid}: ".($r['error']??json_encode($r)));
        }
    }

    lh('RESUMEN');
    lo("Resultados cargados: $auto");
    if ($skip) li("Respetados (ya existían): $skip");
    if ($auto > 0) li("El bracket R32 se auto-generó. Siguiente: bot_fase_grupos.php?paso=todo&ronda=R32...");
}

echo "\n".cc('1;32','✅ FIN')."\n";
