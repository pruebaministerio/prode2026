<?php
/**
 * ================================================================
 *  BOT 2 — PICKS ELIMINATORIOS (por ronda)
 *  Hace picks de la ronda indicada para todos los bots registrados
 *
 *  Uso:
 *    ?ronda=R32    → 32avos de final
 *    ?ronda=R16    → Octavos
 *    ?ronda=QF     → Cuartos
 *    ?ronda=SF     → Semifinales
 *    ?ronda=FIN    → Final
 *    ?ronda=3RD    → Tercer puesto
 *    ?ronda=todos  → Todas las rondas disponibles
 *
 *  Ejemplo: http://localhost/prode2026/bot2_picks_ko.php?ronda=R32
 * ================================================================
 */
define('BASE_URL',  'http://localhost/prode2026/api.php');
define('ADMIN_USER','huesoplu');
define('ADMIN_PASS','koke4812');
define('BOT_PASS',  'BotPass123!');
define('DELAY_MS',  40);

$RONDA_PARAM = strtoupper(trim($_GET['ronda'] ?? $argv[1] ?? ''));
$RONDAS_LABELS = ['R32'=>'32avos','R16'=>'Octavos','QF'=>'Cuartos',
                  'SF'=>'Semis','FIN'=>'Final','3RD'=>'3er Puesto','TODOS'=>'Todas'];

// ── Output ────────────────────────────────────────────────────────
header('Content-Type: text/plain; charset=utf-8'); ob_implicit_flush();
function cc($c,$m){return "\033[{$c}m{$m}\033[0m";}
function log_ok($m) { echo cc('32','✓')." $m\n"; flush(); }
function log_err($m){ echo cc('31','✗')." $m\n"; flush(); }
function log_info($m){echo cc('36','ℹ')." $m\n"; flush(); }
function log_head($m){echo "\n".cc('1;33',"=== $m ===")."\n"; flush();}

if(!$RONDA_PARAM || !array_key_exists($RONDA_PARAM,$RONDAS_LABELS)){
    echo cc('1;33',"BOT 2 — PICKS KO\n\n");
    echo "Parámetro requerido: ?ronda=\n\n";
    foreach($RONDAS_LABELS as $k=>$v) echo "  ?ronda=$k  →  $v\n";
    echo "\nEjemplo: ?ronda=R32\n";
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

// ── Goleadores pick: máx 3 en total ──────────────────────────────
function sc_pick(string $t1, string $t2, array $pbt): array {
    $pool=array_merge($pbt[$t1]??[],$pbt[$t2]??[]);
    if(!$pool) return [];
    $n=mt_rand(0,min(3,count($pool)));
    if($n===0) return [];
    $ks=(array)array_rand($pool,$n);
    return array_map(fn($k)=>$pool[$k],$ks);
}

function gen_score(): array {
    $r=mt_rand(0,9);
    if($r<4)     return [mt_rand(1,4),mt_rand(0,2)];
    elseif($r<7) return [mt_rand(0,2),mt_rand(1,3)];
    else         {$g=mt_rand(0,2);return[$g,$g];}
}

// ══════════════════════════════════════════════════════════════════
$label = $RONDAS_LABELS[$RONDA_PARAM];
echo cc('1;33',str_repeat('═',54))."\n";
echo cc('1;33',"  BOT 2 — PICKS KO: $label ($RONDA_PARAM)")."\n";
echo cc('1;33',str_repeat('═',54))."\n";

// ── PASO 1: LOGIN ADMIN + JUGADORES ──────────────────────────────
log_head('PASO 1 — Login admin y jugadores');
$lr=api(['action'=>'login','username'=>ADMIN_USER,'password'=>ADMIN_PASS]);
if(empty($lr['ok'])){log_err("Login admin fallido.");exit(1);}
$adm=$lr['token'];
log_ok("Admin OK");

$pr=api(['action'=>'get_players'])['players']??[];
$pbt=[];
foreach($pr as $team=>$list) $pbt[$team]=array_column($list,'name');
log_ok("Equipos con jugadores: ".count($pbt));

// ── PASO 2: LOGUEAR TODOS LOS BOTS ───────────────────────────────
log_head('PASO 2 — Logueando bots');
$ur=api(['action'=>'get_users','token'=>$adm]);
$bots=[];
foreach($ur['users']??[] as $u){
    if(strpos($u['username'],'bot_')!==0) continue;
    $r=api(['action'=>'login','username'=>$u['username'],'password'=>BOT_PASS]);
    if(!empty($r['ok']))
        $bots[]=['nombre'=>$u['nombre'].' '.$u['apellido'],'token'=>$r['token']];
    else
        log_err("  Login fallido: {$u['username']}");
}
log_ok("Bots logueados: ".count($bots));

// ── PASO 3: PICKS POR RONDA ───────────────────────────────────────
$all_ko=api(['action'=>'get_knockout_matches'])['knockout_matches']??[];

$rondas_a_procesar = ($RONDA_PARAM==='TODOS')
    ? ['R32','R16','QF','SF','FIN','3RD']
    : [$RONDA_PARAM];

foreach($rondas_a_procesar as $ronda){
    $partidos=array_values(array_filter($all_ko,fn($k)=>$k['ronda']===$ronda));
    if(!$partidos){
        log_info("Ronda $ronda: sin partidos disponibles todavía.");
        continue;
    }

    log_head("Picks — $ronda (".count($partidos)." partidos)");

    foreach($bots as $bot){
        $ok=0;
        foreach($partidos as $ko){
            $t1=$ko['team1']??''; $t2=$ko['team2']??'';
            [$s1,$s2]=gen_score();
            $r=api(['action'=>'save_knockout_pick','token'=>$bot['token'],
                    'km_id'=>(int)$ko['id'],'s1'=>$s1,'s2'=>$s2,
                    'scorers'=>sc_pick($t1,$t2,$pbt)]);
            if(!empty($r['ok'])) $ok++;
        }
        log_ok(sprintf("  %-28s → %d/%d",$bot['nombre'],$ok,count($partidos)));
    }
}

log_head('LISTO ✓');
log_info("Ahora cargá los resultados con bot3_resultados.php?ronda=$RONDA_PARAM");
