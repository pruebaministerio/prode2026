<?php
/**
 * ================================================================
 *  BOT 1 — FASE DE GRUPOS
 *  Registra N usuarios y hace picks de los 72 partidos con goleadores
 *  Uso: http://localhost/prode2026/bot1_fase_grupos.php?n=20
 * ================================================================
 */
define('BASE_URL',  'http://localhost/prode2026/api.php');
define('BOT_PASS',  'BotPass123!');
define('DELAY_MS',  50);

$NUM_USERS = (int)($_GET['n'] ?? $argv[1] ?? 20);

// ── Output ────────────────────────────────────────────────────────
header('Content-Type: text/plain; charset=utf-8'); ob_implicit_flush();
function cc($c,$m){return "\033[{$c}m{$m}\033[0m";}
function log_ok($m) { echo cc('32','✓')." $m\n"; flush(); }
function log_err($m){ echo cc('31','✗')." $m\n"; flush(); }
function log_info($m){echo cc('36','ℹ')." $m\n"; flush(); }
function log_head($m){echo "\n".cc('1;33',"=== $m ===")."\n"; flush();}

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

// ── Goleadores pick: máx 3 en total, cualquier equipo ────────────
function sc_pick(string $t1, string $t2, array $pbt): array {
    $pool=array_merge($pbt[$t1]??[],$pbt[$t2]??[]);
    if(!$pool) return [];
    $n=mt_rand(0,min(3,count($pool)));
    if($n===0) return [];
    $ks=(array)array_rand($pool,$n);
    return array_map(fn($k)=>$pool[$k],$ks);
}

// ── Marcador aleatorio grupos ─────────────────────────────────────
function gen_score(): array {
    $r=mt_rand(0,9);
    if($r<4)     return [mt_rand(1,4),mt_rand(0,2)];
    elseif($r<7) return [mt_rand(0,2),mt_rand(1,3)];
    else         {$g=mt_rand(0,2);return[$g,$g];}
}

// ── Datos fake ────────────────────────────────────────────────────
$NOMBRES=['Santiago','Valentina','Matías','Camila','Luciano','Florencia',
    'Tomás','Agustina','Nicolás','Martina','Ezequiel','Julieta','Leandro',
    'Sofía','Rodrigo','Micaela','Facundo','Rocío','Ignacio','Natalia',
    'Damián','Lucía','Emiliano','Jimena','Gonzalo','Valeria','Sebastián',
    'Antonella','Federico','Paula','Maximiliano','Celeste','Hernán',
    'Mercedes','Ramiro','Verónica','Joaquín','Brenda','Mariano','Carla'];
$APELLIDOS=['García','González','Rodríguez','López','Martínez','Pérez',
    'Sánchez','Romero','Torres','Flores','Díaz','Reyes','Morales','Herrera',
    'Medina','Ruiz','Castillo','Suárez','Gutiérrez','Acosta','Mendoza',
    'Molina','Álvarez','Vega','Peralta','Ferreyra','Ortega','Benítez'];
$_dnis=[];
function gen_dni():string{ global $_dnis; do{$d=(string)mt_rand(20000000,45000000);}while(in_array($d,$_dnis));$_dnis[]=$d;return $d;}
function gen_user(int $i):array{ global $NOMBRES,$APELLIDOS;
    $n=$NOMBRES[array_rand($NOMBRES)];$a=$APELLIDOS[array_rand($APELLIDOS)];
    return['username'=>'bot_'.strtolower($n).$i,'password'=>BOT_PASS,
           'nombre'=>$n,'apellido'=>$a,'dni'=>gen_dni(),
           'celular'=>'11'.mt_rand(10000000,99999999),'email'=>"bot{$i}@prode.com"];}

// ══════════════════════════════════════════════════════════════════
echo cc('1;33',str_repeat('═',54))."\n";
echo cc('1;33',"  BOT 1 — FASE DE GRUPOS  (n=$NUM_USERS usuarios)")."\n";
echo cc('1;33',str_repeat('═',54))."\n";

// ── PASO 1: REGISTRO ──────────────────────────────────────────────
log_head('PASO 1 — Registrando usuarios');
$users=[];
for($i=1;$i<=$NUM_USERS;$i++){
    $u=gen_user($i);
    $r=api(array_merge(['action'=>'register'],$u));
    if(!empty($r['ok'])){
        log_ok(sprintf("[%3d] %s %s (%s)",$i,$u['nombre'],$u['apellido'],$u['username']));
        $users[]=['data'=>$u,'token'=>$r['token']];
    } else {
        log_err(sprintf("[%3d] %s: %s",$i,$u['username'],$r['error']??json_encode($r)));
    }
}
if(!$users){log_err("Sin usuarios registrados.");exit(1);}
log_ok("Registrados: ".count($users));

// ── PASO 2: PARTIDOS Y JUGADORES ─────────────────────────────────
log_head('PASO 2 — Partidos y jugadores');
$matches=api(['action'=>'get_matches'])['matches']??[];
if(!$matches){log_err("Sin partidos en DB.");exit(1);}
log_ok("Partidos: ".count($matches));

$pr=api(['action'=>'get_players'])['players']??[];
$pbt=[];
foreach($pr as $team=>$list) $pbt[$team]=array_column($list,'name');
log_ok("Equipos con jugadores: ".count($pbt));

// ── PASO 3: PICKS DE GRUPOS ───────────────────────────────────────
log_head('PASO 3 — Picks de fase de grupos');
foreach($users as $u){
    $ok=0;
    foreach($matches as $m){
        [$s1,$s2]=gen_score();
        $r=api(['action'=>'save_pick','token'=>$u['token'],
                'match_id'=>(int)$m['id'],'s1'=>$s1,'s2'=>$s2,
                'scorers'=>sc_pick($m['team1'],$m['team2'],$pbt)]);
        if(!empty($r['ok'])) $ok++;
    }
    log_ok(sprintf("%-28s → %d/%d picks",
        "{$u['data']['nombre']} {$u['data']['apellido']}",$ok,count($matches)));
}

log_head('LISTO ✓');
log_ok("Usuarios registrados: ".count($users));
log_ok("Picks de grupos cargados por usuario: ".count($matches));
log_info("Ahora podés cargar los resultados con bot3_resultados.php?ronda=grupos");
