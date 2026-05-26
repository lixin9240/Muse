<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use OSS\OssClient;

echo "=== OSS 详细诊断 ===\n\n";

// 1. 读取配置
$config = config('filesystems.disks.oss');
$accessKeyId = $config['access_key_id'];
$accessKeySecret = $config['access_key_secret'];
$bucket = $config['bucket'];
$endpoint = $config['endpoint'];
$ssl = $config['ssl'] ?? true;

echo "1. 配置信息:\n";
echo "   AccessKey ID: " . substr($accessKeyId, 0, 12) . "...\n";
echo "   Bucket: {$bucket}\n";
echo "   Endpoint: {$endpoint}\n";
echo "   SSL: " . ($ssl ? 'true' : 'false') . "\n\n";

// 2. 处理 endpoint
$originalEndpoint = $endpoint;
if ($endpoint && !str_starts_with($endpoint, 'http://') && !str_starts_with($endpoint, 'https://')) {
    $endpoint = ($ssl ? 'https://' : 'http://') . $endpoint;
}
echo "2. 处理后的 Endpoint: {$endpoint}\n\n";

// 3. 尝试创建客户端并列出 Buckets
try {
    echo "3. 创建 OssClient...\n";
    $client = new OssClient($accessKeyId, $accessKeySecret, $endpoint, $ssl, false);
    echo "   ✅ 客户端创建成功\n\n";

    echo "4. 尝试列出所有 Buckets...\n";
    $bucketList = $client->listBuckets();
    $buckets = $bucketList->getBucketList();
    echo "   找到 " . count($buckets) . " 个 buckets:\n";
    foreach ($buckets as $b) {
        echo "     - " . $b->getName() . " (" . $b->getLocation() . ")\n";
    }
    echo "\n";

    // 检查目标 bucket 是否在列表中
    $bucketNames = array_map(function($b) { return $b->getName(); }, $buckets);
    if (in_array($bucket, $bucketNames)) {
        echo "   ✅ Bucket '{$bucket}' 在列表中\n\n";
    } else {
        echo "   ❌ Bucket '{$bucket}' 不在列表中!\n";
        echo "      可能原因: AccessKey 没有权限访问此 Bucket\n\n";
    }

    // 5. 尝试直接上传文件（跳过 listBuckets）
    echo "5. 尝试直接上传测试文件到 {$bucket}...\n";
    try {
        $client->putObject($bucket, 'debug-test.txt', 'Hello from debug script');
        echo "   ✅ 直接上传成功!\n\n";
    } catch (\Exception $e) {
        echo "   ❌ 直接上传失败: " . $e->getMessage() . "\n";
        echo "      错误代码: " . $e->getCode() . "\n\n";

        // 尝试不同的 endpoint 格式
        echo "6. 尝试使用虚拟主机格式 (bucket.endpoint)...\n";
        try {
            $virtualHostEndpoint = "https://{$bucket}.oss-cn-chengdu.aliyuncs.com";
            $client2 = new OssClient($accessKeyId, $accessKeySecret, $virtualHostEndpoint, true, false);
            $client2->putObject($bucket, 'debug-test2.txt', 'Test with virtual host');
            echo "   ✅ 虚拟主机格式上传成功!\n\n";
        } catch (\Exception $e2) {
            echo "   ❌ 虚拟主机格式也失败: " . $e2->getMessage() . "\n\n";
        }
    }

} catch (\Exception $e) {
    echo "   ❌ 错误: " . $e->getMessage() . "\n";
    echo "      文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== 诊断完成 ===";
