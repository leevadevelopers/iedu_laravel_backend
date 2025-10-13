<?php
/**
 * A2 Hosting Configuration Helper
 * Run this file once after deployment to fix permissions and configuration
 * Access: https://iedu.nakasha.store/a2hosting-config.php
 */

// Only allow this to run once
if (file_exists(__DIR__ . '/.a2hosting-configured')) {
    die('Already configured. Delete this file for security.');
}

echo "<h2>Configurando Laravel para A2 Hosting...</h2>";

// Fix permissions
$directories = ['storage', 'bootstrap/cache'];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "<p>Configurando permissões para: $dir</p>";
        chmod($dir, 0755);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                chmod($path, 0755);
            } else {
                chmod($path, 0644);
            }
        }
    }
}

// Create .env if it doesn't exist
if (!file_exists('.env')) {
    if (file_exists('.env.example')) {
        copy('.env.example', '.env');
        echo "<p>Arquivo .env criado a partir do .env.example</p>";
    }
}

// Generate application key if needed
if (file_exists('.env')) {
    $env = file_get_contents('.env');
    if (strpos($env, 'APP_KEY=') === false || strpos($env, 'APP_KEY=') !== false && trim(explode('APP_KEY=', $env)[1]) === '') {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $env = str_replace('APP_KEY=', 'APP_KEY=' . $key, $env);
        file_put_contents('.env', $env);
        echo "<p>Chave da aplicação gerada</p>";
    }
}

// Create storage directories
$storageDirs = [
    'storage/app',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs'
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "<p>Diretório criado: $dir</p>";
    }
}

// Create configuration marker
file_put_contents(__DIR__ . '/.a2hosting-configured', date('Y-m-d H:i:s'));
echo "<h3>✅ Configuração concluída!</h3>";
echo "<p><strong>IMPORTANTE:</strong> Delete este arquivo (a2hosting-config.php) por segurança!</p>";
echo "<p>Seu Laravel deve estar funcionando agora em: <a href='/'>iedu.nakasha.store</a></p>";
?>
