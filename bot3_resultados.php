<?php
/**
 * ================================================================
 *  BOT 3 — RESULTADOS ADMIN (con goleadores)
 *  Carga resultados automáticos SOLO para partidos sin resultado.
 *  Si un partido ya tiene resultado (cargado manualmente), lo respeta.
 *
 *  Uso:
 *    ?ronda=grupos  → Los 72 partidos de fase de grupos
 *    ?ronda=R32     → 32avos de final
 *    ?ronda=R16     → Octavos
 *    ?ronda=QF      → Cuartos
 *    ?ronda=SF      → Semifinales
 *    ?ronda=FIN     → Final
 *    ?ronda=3RD     → Tercer puesto
 *
 *  Si ya cargaste algunos resultados manualmente desde la app,
 *  este bot los detecta y los deja intactos.
 *
 *  Ejemplo: http://localhost/prode2026/bot3_resultados.php?ronda=grupos
 * ================================================================
 */
define('BASE_URL',  'http://localhost/prode2026/api.php');
define('ADMIN_USER','huesoplu');
define('ADMIN_PASS','koke4812');
define('DELAY_MS',  50);

$RONDA_PARAM = strtolower(trim($_GET['ronda'] ?? $argv[1] ?? ''));
$RONDAS_VALIDAS = ['grupos','r32','r16','qf','sf','fin','3rd'];

// ── Output ────────────────────────────────────────────────────────
header('Content-Type: text/plain; charset=utf-8'); ob_implicit_flush();
function cc($c,$m){return "\033[{$c}m{$m}\033[0m";}
function log_ok($m)   { echo cc('32','✓')." $m\n"; flush(); }
function log_err($m)  { echo cc('31','✗')." $m\n"; flush(); }
function log_info($m) { echo cc('36','ℹ')." $m\n"; flush(); }
function log_skip($m) { echo cc('33','⏭')." $m\n"; flush(); }
function log_head($m) { echo "\n".cc('1;33',"=== $m ===")."\n"; flush(); }

if(!$RONDA_PARAM || !in_array($RONDA_PARAM,$RONDAS_VALIDAS)){
    echo cc('1;33',"BOT 3 — RESULTADOS ADMIN\n\n");
    echo "Parámetro requerido: ?ronda=\n\n";
    echo "  ?ronda=grupos  → Fase de grupos (72 partidos)\n";
    echo "  ?ronda=R32     → 32avos de final\n";
    echo "  ?ronda=R16     → Octavos de final\n";
    echo "  ?ronda=QF      → Cuartos de final\n";
    echo "  ?ronda=SF      → Semifinales\n";
    echo "  ?ronda=FIN     → Final\n";
    echo "  ?ronda=3RD     → Tercer puesto\n\n";
    echo "Los partidos que ya tienen resultado manual NO se tocan.\n";
    exit(0);
}

// ── HTTP ──────────────────────────────────────────────────────────
function api(array $p): array {
    $body=json_encode($p,JSON_UNESCAPED_UNICODE);
    $ch=curl_init(BASE_URL);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
    $raw=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
    usleep(DELAY_MS*1000);
    if($err) return ['ok'=>false,'error'=>$err];
    return json_decode($raw,true)??['ok'=>false,'raw'=>substr($raw,0,200)];
}

// ── Goleadores resultado: tantos como goles, sin límite de 3 ─────
function sc_resultado(string $team, int $goals, array $pbt): array {
    $pl=$pbt[$team]??[];
    if(!$pl||$goals<=0) return [];
    $n=min($goals,count($pl));
    $ks=(array)array_rand($pl,$n);
    return array_map(fn($k)=>$pl[$k],$ks);
}

// ── Marcadores ────────────────────────────────────────────────────
function gen_grupo(): array {
    $r=mt_rand(0,9);
    if($r<4)     return [mt_rand(1,4),mt_rand(0,2)];
    elseif($r<7) return [mt_rand(0,2),mt_rand(1,3)];
    else         {$g=mt_rand(0,2);return[$g,$g];}
}

function gen_ko(string $t1, string $t2): array {
    if(mt_rand(0,1)){
        do{$h=mt_rand(0,4);$a=mt_rand(0,3);}while($h===$a);
        return['s1'=>$h,'s2'=>$a,'pen1'=>null,'pen2'=>null,'winner'=>$h>$a?$t1:$t2];
    }
    $g=mt_rand(0,2);
    do{$p1=mt_rand(2,6);$p2=mt_rand(2,6);}while($p1===$p2);
    return['s1'=>$g,'s2'=>$g,'pen1'=>$p1,'pen2'=>$p2,'winner'=>$p1>$p2?$t1:$t2];
}

// ══════════════════════════════════════════════════════════════════
echo cc('1;33',str_repeat('═',56))."\n";
echo cc('1;33',"  BOT 3 — RESULTADOS ADMIN: ".strtoupper($RONDA_PARAM))."\n";
echo cc('1;33',str_repeat('═',56))."\n";
log_info("Los partidos con resultado manual NO se modifican.");

// ── LOGIN ADMIN ───────────────────────────────────────────────────
log_head('Login admin');
$lr=api(['action'=>'login','username'=>ADMIN_USER,'password'=>ADMIN_PASS]);
if(empty($lr['ok'])){log_err("Login fallido: ".($lr['error']??''));exit(1);}
$adm=$lr['token'];
log_ok("Admin OK");

// ── JUGADORES ─────────────────────────────────────────────────────
$pr=api(['action'=>'get_players'])['players']??[];
$pbt=[];
foreach($pr as $team=>$list) $pbt[$team]=array_column($list,'name');
log_ok("Equipos con jugadores: ".count($pbt));

// ══════════════════════════════════════════════════════════════════
// FASE DE GRUPOS
// ══════════════════════════════════════════════════════════════════
if($RONDA_PARAM==='grupos'){
    log_head('Resultados de grupos');

    $matches = api(['action'=>'get_matches'])['matches']??[];
    $results = api(['action'=>'get_results'])['results']??[];
    $mById   = array_column($matches,null,'id');

    $auto=0; $skip=0;
    foreach($matches as $m){
        $mid=(int)$m['id'];
        // ¿Ya tiene resultado?
        if(isset($results[$mid]) && $results[$mid]['s1']!==null){
            log_skip(sprintf("Grp %s #%d  %s vs %s → ya tiene resultado (%d–%d), respetando.",
                $m['grp'],$mid,$m['team1'],$m['team2'],
                $results[$mid]['s1'],$results[$mid]['s2']));
            $skip++;
            continue;
        }
        // Generar resultado automático
        [$s1,$s2]=gen_grupo();
        $sc=array_merge(
            sc_resultado($m['team1'],$s1,$pbt),
            sc_resultado($m['team2'],$s2,$pbt)
        );
        $r=api(['action'=>'set_result','token'=>$adm,
                'match_id'=>$mid,'s1'=>$s1,'s2'=>$s2,'scorers'=>$sc]);
        if(!empty($r['ok'])){
            $auto++;
            $gls=implode(', ',$sc)?:'—';
            log_ok(sprintf("Grp %s #%d  %s %d–%d %s · ⚽ %s",
                $m['grp'],$mid,$m['team1'],$s1,$s2,$m['team2'],$gls));
        } else {
            log_err("Partido $mid: ".($r['error']??json_encode($r)));
        }
    }
    log_head("LISTO");
    log_ok("Cargados automáticamente: $auto");
    log_info("Respetados (manuales): $skip");
    if($auto>0) log_info("El R32 se auto-genera al completar los 72 resultados.");
    exit(0);
}

// ══════════════════════════════════════════════════════════════════
// RONDAS ELIMINATORIAS
// ══════════════════════════════════════════════════════════════════
$ronda_upper=strtoupper($RONDA_PARAM);
log_head("Resultados KO — $ronda_upper");

$all_ko=api(['action'=>'get_knockout_matches'])['knockout_matches']??[];
$partidos=array_values(array_filter($all_ko,fn($k)=>$k['ronda']===$ronda_upper));

if(!$partidos){
    log_err("No hay partidos para $ronda_upper. ¿Ya se generó esta ronda?");
    log_info("Primero cargá los resultados de la ronda anterior.");
    exit(1);
}
log_ok("Partidos encontrados: ".count($partidos));

$auto=0; $skip=0; $tbd=0;
foreach($partidos as $ko){
    $id=(int)$ko['id'];
    $t1=$ko['team1']??'TBD'; $t2=$ko['team2']??'TBD';

    // Sin equipos definidos aún
    if($t1==='TBD'||$t2==='TBD'||!$t1||!$t2){
        log_info("Partido $id: equipos no definidos todavía (TBD).");
        $tbd++;
        continue;
    }

    // ¿Ya tiene resultado?
    if($ko['winner']!==null){
        log_skip("$t1 vs $t2 → ya tiene resultado ({$ko['s1']}–{$ko['s2']}, ganador: {$ko['winner']}), respetando.");
        $skip++;
        continue;
    }

    // Generar resultado KO automático
    $sc=gen_ko($t1,$t2);
    $sc1=sc_resultado($t1,$sc['s1'],$pbt);
    $sc2=sc_resultado($t2,$sc['s2'],$pbt);

    $r=api(['action'=>'set_knockout_result','token'=>$adm,
            'km_id'=>$id,'team1'=>$t1,'team2'=>$t2,
            's1'=>$sc['s1'],'s2'=>$sc['s2'],
            'pen1'=>$sc['pen1'],'pen2'=>$sc['pen2'],
            'scorers1'=>$sc1,'scorers2'=>$sc2]);

    $pen=$sc['pen1']!==null?" (pen.{$sc['pen1']}-{$sc['pen2']})":"";
    $g1=implode(', ',$sc1)?:'—';
    $g2=implode(', ',$sc2)?:'—';

    if(!empty($r['ok'])){
        $auto++;
        log_ok("$t1 {$sc['s1']}–{$sc['s2']} $t2$pen → 🏆 {$r['winner']}");
        log_info("  ⚽ $t1: $g1");
        log_info("  ⚽ $t2: $g2");
    } else {
        log_err("KO $id: ".($r['error']??json_encode($r)));
    }
}

log_head("LISTO");
log_ok("Cargados automáticamente: $auto");
if($skip) log_info("Respetados (manuales): $skip");
if($tbd)  log_info("Sin equipos definidos (TBD): $tbd");
if($auto>0 && in_array($ronda_upper,['R32','R16','QF','SF']))
    log_info("La siguiente ronda se auto-generó. Corré bot2 y luego bot3 para continuar.");
