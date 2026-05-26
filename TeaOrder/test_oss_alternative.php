<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use OSS\OssClient;

$config = config('filesystems.disks.oss');

echo "=== 测试不同 Endpoint 格式 ===\n\n";

// 测试 1: 标准格式
echo "1. 标准格式 (https://oss-cn-chengdu.aliyuncs.com):\n";
try {
    $client = new OssClient($config['access_key_id'], $config['access_key_secret'], 'https://oss-cn-chengdu.aliyuncs.com', true, false);
    $client->putObject($config['bucket'], 'test1.txt', 'Test 1');
    echo "   ✅ 成功\n\n";
} catch (Exception $e) {
    echo "   ❌ 失败: " . $e->getMessage() . "\n\n";
}

// 测试 2: 虚拟主机格式 (bucket.endpoint)
echo "2. 虚拟主机格式 (https://oedertea.oss-cn-chengdu.aliyuncs.com):\n";
try {
    $client = new OssClient($config['access_key_id'], $config['access_key_secret'], 'https://oedertea.oss-cn-chengdu.aliyuncs.com', true, false);
    $client->putObject($config['bucket'], 'test2.txt', 'Test 2');
    echo "   ✅ 成功\n\n";
} catch (Exception $e) {
    echo "   ❌ 失败: " . $e->getMessage() . "\n\n";
}

// 测试 3: 不使用 HTTPS，只用 HTTP
echo "3. HTTP 格式 (http://oss-cn-chengdu.aliyuncs.com):\n";
try {
    $client = new OssClient($config['access_key_id'], $config['access_key_secret'], 'http://oss-cn-chengdu.aliyuncs.com', false, false);
    $client->putObject($config['bucket'], 'test3.txt', 'Test 3');
    echo "   ✅ 成功\n\n";
} catch (Exception $e) {
    echo "   ❌ 失败: " . $e->getMessage() . "\n\n";
}

// 测试 4: 使用内网 Endpoint
echo "4. 内网 Endpoint (https://oss-cn-chengdu-internal.aliyuncs.com):\n";
try {
    $client = new OssClient($config['access_key_id'], $config['access_key_secret'], 'https://oss-cn-chengdu-internal.aliyuncs.com', true, false);
    $client->putObject($config['bucket'], 'test4.txt', 'Test 4');
    echo "   ✅ 成功\n\n";
} catch (Exception $e) {
    echo "   ❌ 失败: " . $e->getMessage() . "\n\n";
}

echo "=== 测试完成 ===";
