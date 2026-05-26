<?php
/**
 * ================================================================
 *  BOT KO COMPLETO — Prode Vale 4 · Mundial USA 2026
 * ================================================================
 *  Uso: http://localhost/prode2026/bot_ko_completo.php
 *
 *  Los jugadores ya están cargados en la DB.
 *  Este script:
 *   1. Lee los jugadores desde la DB
 *   2. Actualiza los resultados de grupos con goleadores reales
 *   3. Por cada ronda KO (R32→R16→QF→SF→FIN+3RD):
 *      a. Bots actualizan picks de esa ronda con goleadores
 *      b. Admin carga resultado con goleadores → siguiente ronda
 *      c. Bots hacen picks de la siguiente ronda con goleadores
 * ================================================================
 */

define('BASE_URL',  'http://localhost/prode2026/api.php');
define('ADMIN_USER','huesoplu');
define('ADMIN_PASS','koke4812');
define('BOT_PASS',  'BotPass123!');
define('DELAY_MS',  40);

// ── Output ────────────────────────────────────────────────────────
$isCli = php_sapi_name() === 'cli';
if (!$isCli) { header('Content-Type: text/plain; charset=utf-8'); ob_implicit_flush(); }
function cc(string $c, string $m): string { global $isCli; return $isCli?"\033[{$c}m{$m}\033[0m":$m; }
function log_ok(string $m)  : void { echo cc('32','✓')." $m\n"; flush(); }
function log_err(string $m) : void { echo cc('31','✗')." $m\n"; flush(); }
function log_info(string $m): void { echo cc('36','ℹ')." $m\n"; flush(); }
function log_head(string $m): void { echo "\n".cc('1;33',"=== $m ===")."\n"; flush(); }

// ── HTTP ──────────────────────────────────────────────────────────
function api(array $payload): array {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch   = curl_init(BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    usleep(DELAY_MS * 1000);
    if ($err) return ['ok'=>false,'error'=>$err];
    return json_decode($raw, true) ?? ['ok'=>false,'raw'=>substr($raw,0,200)];
}

// ── Goleadores para RESULTADOS: por equipo, tantos como goles ────
//    El admin registra quién convirtió (sin límite de 3, es el real)
function sc_resultado(string $team, int $goals, array $pbt): array {
    $pl = $pbt[$team] ?? [];
    if (!$pl || $goals <= 0) return [];
    $n  = min($goals, count($pl));
    $ks = (array)array_rand($pl, $n);
    return array_map(fn($k) => $pl[$k], $ks);
}

// ── Goleadores para PICKS: máx 3 EN TOTAL (regla del prode) ──────
//    El usuario elige hasta 3 jugadores de CUALQUIER equipo,
//    sin importar el marcador pronosticado.
function sc_pick(string $t1, string $t2, array $pbt): array {
    $pool = array_merge($pbt[$t1] ?? [], $pbt[$t2] ?? []);
    if (!$pool) return [];
    $n  = mt_rand(0, min(3, count($pool)));   // 0, 1, 2 o 3
    if ($n === 0) return [];
    $ks = (array)array_rand($pool, $n);
    return array_map(fn($k) => $pool[$k], $ks);
}
// ── Marcadores aleatorios ─────────────────────────────────────────
function gen_grupo(): array {
    $r = mt_rand(0,9);
    if ($r<4)     return [mt_rand(1,4), mt_rand(0,2)];
    elseif ($r<7) return [mt_rand(0,2), mt_rand(1,3)];
    else          { $g=mt_rand(0,2); return [$g,$g]; }
}

function gen_ko(string $t1, string $t2): array {
    if (mt_rand(0,1)) {
        // resultado en 90'
        do { $h=mt_rand(0,4); $a=mt_rand(0,3); } while ($h===$a);
        return ['s1'=>$h,'s2'=>$a,'pen1'=>null,'pen2'=>null,'winner'=>$h>$a?$t1:$t2];
    }
    // empate + penales
    $g=mt_rand(0,2);
    do { $p1=mt_rand(2,6); $p2=mt_rand(2,6); } while ($p1===$p2);
    return ['s1'=>$g,'s2'=>$g,'pen1'=>$p1,'pen2'=>$p2,'winner'=>$p1>$p2?$t1:$t2];
}

// ══════════════════════════════════════════════════════════════════
echo cc('1;33',str_repeat('═',58))."\n";
echo cc('1;33',"  BOT KO COMPLETO — Prode Vale 4 · Mundial USA 2026")."\n";
echo cc('1;33',str_repeat('═',58))."\n";

// ══════════════════════════════════════════════════════════════════
// PASO 1: LOGIN ADMIN
// ══════════════════════════════════════════════════════════════════
log_head('PASO 1 — Login admin');
$resp = api(['action'=>'login','username'=>ADMIN_USER,'password'=>ADMIN_PASS]);
if (empty($resp['ok'])) { log_err("Login fallido: ".($resp['error']??json_encode($resp))); exit(1); }
$adm = $resp['token'];
log_ok("Admin logueado · token: ".substr($adm,0,8)."...");

// ══════════════════════════════════════════════════════════════════
// PASO 2: LEER JUGADORES DESDE LA DB
// ══════════════════════════════════════════════════════════════════
log_head('PASO 2 — Leyendo jugadores de la DB');

// get_players retorna: {'players': {'Equipo': [{'id':N,'name':'...'}]}}
$pr  = api(['action'=>'get_players']);
$pbt = [];   // ['Equipo' => ['nombre1', 'nombre2', ...]]
foreach ($pr['players'] ?? [] as $team => $list) {
    $pbt[$team] = array_column($list, 'name');
}

if (empty($pbt)) {
    log_err("No hay jugadores en la DB. Cargalos desde Setup → Plantillas.");
    exit(1);
}
log_ok("Equipos con jugadores: ".count($pbt));
foreach ($pbt as $team => $pl) {
    log_info("  $team: ".count($pl)." jugadores");
}

// ══════════════════════════════════════════════════════════════════
// PASO 3: LOGUEAR TODOS LOS BOTS
// ══════════════════════════════════════════════════════════════════
log_head('PASO 3 — Logueando bots');

$ur       = api(['action'=>'get_users','token'=>$adm]);
$allUsers = $ur['users'] ?? [];
$bots     = [];   // [['nombre'=>..., 'token'=>...]]

foreach ($allUsers as $u) {
    if (strpos($u['username'], 'bot_') !== 0) continue;   // omitir usuarios reales
    $lr = api(['action'=>'login','username'=>$u['username'],'password'=>BOT_PASS]);
    if (!empty($lr['ok'])) {
        $bots[] = ['nombre'=>$u['nombre'].' '.$u['apellido'], 'token'=>$lr['token']];
    } else {
        log_err("  Login fallido: {$u['username']}");
    }
}
log_ok("Bots logueados: ".count($bots)." / ".count($allUsers));

// ══════════════════════════════════════════════════════════════════
// PASO 4: ACTUALIZAR RESULTADOS DE GRUPOS CON GOLEADORES
//         (re-envía el mismo marcador pero agrega scorers de la DB)
// ══════════════════════════════════════════════════════════════════
log_head('PASO 4 — Resultados de grupos + goleadores');

$matches  = api(['action'=>'get_matches'])['matches'] ?? [];
$results  = api(['action'=>'get_results'])['results'] ?? [];
$mById    = array_column($matches, null, 'id');

$updated = 0;
foreach ($results as $mid => $r) {
    $m   = $mById[$mid] ?? null;
    if (!$m) continue;
    $sc1 = sc_resultado($m['team1'], (int)$r['s1'], $pbt);
    $sc2 = sc_resultado($m['team2'], (int)$r['s2'], $pbt);
    $resp = api([
        'action'   => 'set_result',
        'token'    => $adm,
        'match_id' => (int)$mid,
        's1'       => (int)$r['s1'],
        's2'       => (int)$r['s2'],
        'scorers'  => array_merge($sc1, $sc2),
    ]);
    if (!empty($resp['ok'])) {
        $updated++;
        $sc_txt = implode(', ', array_merge($sc1, $sc2)) ?: '—';
        log_ok(sprintf("  Grp %s #%d  %s %d–%d %s · ⚽ %s",
            $m['grp'], $mid, $m['team1'], $r['s1'], $r['s2'], $m['team2'], $sc_txt));
    } else {
        log_err("  Partido $mid: ".($resp['error']??json_encode($resp)));
    }
}
log_ok("Resultados de grupos actualizados: $updated");

// ══════════════════════════════════════════════════════════════════
// PASOS 5+: RONDAS ELIMINATORIAS
// ══════════════════════════════════════════════════════════════════
$RONDAS_KO = ['R32','R16','QF','SF','FIN','3RD'];

foreach ($RONDAS_KO as $ronda) {
    log_head("RONDA $ronda");

    // Obtener partidos de esta ronda
    $all_ko   = api(['action'=>'get_knockout_matches'])['knockout_matches'] ?? [];
    $this_rnd = array_values(array_filter($all_ko, fn($k)=>$k['ronda']===$ronda));

    if (empty($this_rnd)) {
        log_info("No hay partidos para $ronda todavía — saltando.");
        continue;
    }
    log_info("Partidos en $ronda: ".count($this_rnd));

    // ── A: Bots actualizan picks de esta ronda CON goleadores ─────
    log_info("  Actualizando picks de $ronda con goleadores...");
    foreach ($bots as $bot) {
        $ok_c = 0;
        foreach ($this_rnd as $ko) {
            $t1 = $ko['team1'] ?? ''; $t2 = $ko['team2'] ?? '';
            [$s1,$s2] = gen_grupo();
            $sc = sc_pick($t1, $t2, $pbt);   // máx 3 en total, cualquier equipo
            $resp = api([
                'action'  => 'save_knockout_pick',
                'token'   => $bot['token'],
                'km_id'   => (int)$ko['id'],
                's1'      => $s1,
                's2'      => $s2,
                'scorers' => $sc,
            ]);
            if (!empty($resp['ok'])) $ok_c++;
        }
        log_ok(sprintf("  %-28s → %d/%d picks", $bot['nombre'], $ok_c, count($this_rnd)));
    }

    // ── B: Admin carga resultados de esta ronda CON goleadores ────
    log_info("  Cargando resultados de $ronda...");
    foreach ($this_rnd as $ko) {
        $t1 = $ko['team1'] ?? 'TBD'; $t2 = $ko['team2'] ?? 'TBD';
        if ($t1==='TBD' || $t2==='TBD') {
            log_info("  Partido {$ko['id']} sin equipos — saltando.");
            continue;
        }

        $sc = gen_ko($t1, $t2);
        $sc1 = sc_resultado($t1, $sc['s1'], $pbt);
        $sc2 = sc_resultado($t2, $sc['s2'], $pbt);

        $resp = api([
            'action'   => 'set_knockout_result',
            'token'    => $adm,
            'km_id'    => (int)$ko['id'],
            'team1'    => $t1,
            'team2'    => $t2,
            's1'       => $sc['s1'],
            's2'       => $sc['s2'],
            'pen1'     => $sc['pen1'],
            'pen2'     => $sc['pen2'],
            'scorers1' => $sc1,
            'scorers2' => $sc2,
        ]);

        $pen  = $sc['pen1']!==null ? " (pen.{$sc['pen1']}-{$sc['pen2']})" : '';
        $gls1 = implode(', ', $sc1) ?: '—';
        $gls2 = implode(', ', $sc2) ?: '—';

        if (!empty($resp['ok'])) {
            log_ok("  $t1 {$sc['s1']}–{$sc['s2']} $t2$pen → 🏆 {$resp['winner']}");
            log_info("    ⚽ $t1: $gls1");
            log_info("    ⚽ $t2: $gls2");
        } else {
            log_err("  KO {$ko['id']}: ".($resp['error']??json_encode($resp)));
        }
    }

    // ── C: Bots hacen picks de la SIGUIENTE ronda (si existe) ─────
    // (La siguiente ronda se auto-genera al cargar el último resultado)
    $nextMap = ['R32'=>'R16','R16'=>'QF','QF'=>'SF','SF'=>['FIN','3RD'],'FIN'=>null,'3RD'=>null];
    $nextRnd = $nextMap[$ronda] ?? null;
    if (!$nextRnd) continue;

    // Esperar un momento para que la DB procese la generación
    usleep(200_000);

    $all_ko2 = api(['action'=>'get_knockout_matches'])['knockout_matches'] ?? [];
    $nextRnds = is_array($nextRnd) ? $nextRnd : [$nextRnd];

    foreach ($nextRnds as $nr) {
        $next_ms = array_values(array_filter($all_ko2, fn($k)=>$k['ronda']===$nr));
        if (empty($next_ms)) { log_info("  Ronda $nr aún no generada."); continue; }
        log_info("  Pre-picks de $nr (".count($next_ms)." partidos)...");

        foreach ($bots as $bot) {
            foreach ($next_ms as $ko) {
                $t1 = $ko['team1'] ?? ''; $t2 = $ko['team2'] ?? '';
                [$s1,$s2] = gen_grupo();
                $sc = sc_pick($ko['team1']??'', $ko['team2']??'', $pbt);
                api(['action'=>'save_knockout_pick','token'=>$bot['token'],
                     'km_id'=>(int)$ko['id'],'s1'=>$s1,'s2'=>$s2,'scorers'=>$sc]);
            }
        }
        log_ok("  Picks de $nr cargados para ".count($bots)." bots");
    }
}

// ══════════════════════════════════════════════════════════════════
// RESUMEN
// ══════════════════════════════════════════════════════════════════
log_head('COMPLETADO 🏆');
log_ok("Bots procesados:   ".count($bots));
log_ok("Equipos con jugadores: ".count($pbt));
log_info("Abrí la app y chequeá la Tabla y el Bracket.");
echo "\n";