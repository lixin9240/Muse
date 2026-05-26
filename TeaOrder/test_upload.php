<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use OSS\OssClient;

$config = config('filesystems.disks.oss');
$endpoint = 'https://oedertea.oss-cn-chengdu.aliyuncs.com';

echo "Testing upload...\n";
echo "AccessKey: " . substr($config['access_key_id'], 0, 12) . "...\n";
echo "Bucket: " . $config['bucket'] . "\n";
echo "Endpoint: {$endpoint}\n\n";

try {
    $client = new OssClient($config['access_key_id'], $config['access_key_secret'], $endpoint, true, false);
    $client->putObject($config['bucket'], 'direct-test.txt', 'Hello World');
    echo "✅ SUCCESS! File uploaded.\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
