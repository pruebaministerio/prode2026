<?php
// bot_register_users.php — Registra 5 usuarios de prueba y notifica pago
// Ejecutar UNA sola vez: http://localhost/prode2026/bot_register_users.php

$API = 'http://localhost/prode2026/api.php';

function call(string $api, array $data): array {
    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? ['ok' => false, 'error' => 'No response'];
}

$users = [
    ['username'=>'rodrigues88', 'password'=>'prode2026!', 'nombre'=>'Lucas',    'apellido'=>'Rodríguez', 'dni'=>'38201456', 'celular'=>'1155234567', 'email'=>'lucas.rodriguez@mail.com'],
    ['username'=>'gomezpaula',  'password'=>'prode2026!', 'nombre'=>'Paula',    'apellido'=>'Gómez',     'dni'=>'41305678', 'celular'=>'2236781234', 'email'=>'paula.gomez@mail.com'],
    ['username'=>'fernandezmx', 'password'=>'prode2026!', 'nombre'=>'Maximiliano','apellido'=>'Fernández','dni'=>'35498012','celular'=>'1167890123', 'email'=>'max.fernandez@mail.com'],
    ['username'=>'lopezsofia',  'password'=>'prode2026!', 'nombre'=>'Sofía',    'apellido'=>'López',     'dni'=>'44112233', 'celular'=>'3516543210', 'email'=>'sofia.lopez@mail.com'],
    ['username'=>'martinezr99', 'password'=>'prode2026!', 'nombre'=>'Ramiro',   'apellido'=>'Martínez',  'dni'=>'39876543', 'celular'=>'1144556677', 'email'=>'ramiro.martinez@mail.com'],
];

echo "<pre style='font-family:monospace;font-size:13px;padding:20px'>";
echo "=== REGISTRO DE USUARIOS DE PRUEBA ===\n\n";

foreach ($users as $u) {
    echo "► Registrando @{$u['username']} ({$u['nombre']} {$u['apellido']})... ";

    $r = call($API, array_merge(['action' => 'register'], $u));

    if (!$r['ok']) {
        echo "⚠ SKIP ({$r['error']})\n";

        // Si ya existe, intentamos login para obtener token
        $login = call($API, ['action'=>'login','username'=>$u['username'],'password'=>$u['password']]);
        if (!$login['ok']) { echo "  └ Login también falló: {$login['error']}\n"; continue; }
        $token = $login['token'];
        echo "  (ya existía, usando token de login)\n";
    } else {
        $token = $r['token'];
        echo "✓ Registrado\n";
    }

    // Notificar pago
    echo "  └ Notificando pago... ";
    $n = call($API, ['action' => 'notify_payment', 'token' => $token]);
    if ($n['ok'])        echo "✅ WhatsApp enviado\n";
    elseif (strpos($n['error'] ?? '', '2 horas') !== false)
                         echo "⏭ Ya notificado antes (cooldown)\n";
    else                 echo "⚠ {$n['error']}\n";

    echo "\n";
}

echo "=== LISTO — ahora activá los usuarios desde Setup → Usuarios ===\n";
echo "</pre>";
