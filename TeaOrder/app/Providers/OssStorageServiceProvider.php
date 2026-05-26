<?php
// app/Providers/OssStorageServiceProvider.php
// OSS存储服务提供者

namespace App\Providers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use OSS\OssClient;

class OssStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Storage::extend('oss', function ($app, $config) {
            $ssl = $config['ssl'] ?? true;
            $endpoint = $config['endpoint'];
            $originalEndpoint = $endpoint;

            if ($endpoint && !str_starts_with($endpoint, 'http://') && !str_starts_with($endpoint, 'https://')) {
                $endpoint = ($ssl ? 'https://' : 'http://') . $endpoint;
                logger()->info('OSS Endpoint 自动添加协议前缀', [
                    'original' => $originalEndpoint,
                    'processed' => $endpoint,
                    'ssl' => $ssl,
                ]);
            }

            try {
                $client = new OssClient(
                    $config['access_key_id'],
                    $config['access_key_secret'],
                    $endpoint,
                    $ssl,
                    $config['is_cname'] ?? false
                );

                logger()->info('OSS 客户端初始化成功', [
                    'bucket' => $config['bucket'] ?? '',
                    'endpoint' => $endpoint,
                ]);

                return new \App\Services\OssFilesystemAdapter($client, $config);
            } catch (\Exception $e) {
                logger()->error('OSS 客户端初始化失败', [
                    'bucket' => $config['bucket'] ?? '',
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });
    }
}
