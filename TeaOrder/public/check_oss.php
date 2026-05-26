<?php
// 公开访问的 OSS 配置检查脚本
// 访问: http://localhost:8000/check_oss.php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Web 环境 OSS 配置检查 ===\n\n";

echo "1. 直接从 env() 读取:\n";
echo "   OSS_ACCESS_KEY_ID: " . substr(env('OSS_ACCESS_KEY_ID'), 0, 12) . "****\n";
echo "   OSS_ACCESS_KEY_SECRET: " . substr(env('OSS_ACCESS_KEY_SECRET'), 0, 8) . "****\n";
echo "   OSS_ENDPOINT: " . env('OSS_ENDPOINT') . "\n";
echo "   OSS_BUCKET: " . env('OSS_BUCKET') . "\n\n";

echo "2. 从 config() 读取:\n";
$ossConfig = config('filesystems.disks.oss');
echo "   access_key_id: " . substr($ossConfig['access_key_id'], 0, 12) . "****\n";
echo "   access_key_secret: " . substr($ossConfig['access_key_secret'], 0, 8) . "****\n";
echo "   endpoint: " . $ossConfig['endpoint'] . "\n";
echo "   bucket: " . $ossConfig['bucket'] . "\n\n";

echo "3. 测试上传:\n";
try {
    $result = \Illuminate\Support\Facades\Storage::disk('oss')->put('web-test.txt', 'Test from web');
    echo "   上传结果: " . ($result ? '✅ 成功' : '❌ 失败') . "\n";
} catch (\Exception $e) {
    echo "   错误: " . $e->getMessage() . "\n";
}

echo "\n=== 检查完成 ===";
