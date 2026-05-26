<?php
// app/Services/OssFilesystemAdapter.php
// OSS文件系统适配器

namespace App\Services;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use OSS\OssClient;

class OssFilesystemAdapter implements FilesystemAdapter
{
    /** @var mixed */
    protected $client;
    protected array $config;
    protected string $bucket;

    public function __construct($client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
        $this->bucket = $config['bucket'];
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $path);
        } catch (\Exception) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return true;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $options = [];
            if ($config->get('visibility') === 'public') {
                $options['headers'] = ['x-oss-object-acl' => 'public-read'];
            }
            $this->client->putObject($this->bucket, $path, $contents, $options);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
    }

    public function writeStream(string $path, mixed $contents, Config $config): void
    {
        try {
            $options = [];
            if ($config->get('visibility') === 'public') {
                $options['headers'] = ['x-oss-object-acl' => 'public-read'];
            }
            $this->client->uploadFile($this->bucket, $path, stream_get_meta_data($contents)['uri'], $options);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
    }

    public function read(string $path): string
    {
        try {
            return $this->client->getObject($this->bucket, $path);
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path): mixed
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'oss_');
            $this->client->getObject($this->bucket, $path, ['fileDownload' => $tempFile]);
            
            $stream = fopen($tempFile, 'r');
            unlink($tempFile);
            
            return $stream;
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void

    {
        try {
            $this->client->deleteObject($this->bucket, $path);
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage());
        }
    }

    public function deleteDirectory(string $path): void
    {
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->putObject($this->bucket, rtrim($path, '/') . '/', '');
        } catch (\Exception $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $acl = $visibility === 'public' ? 'public-read' : 'private';
            $options = ['headers' => ['x-oss-acl' => $acl]];
            $this->client->copyObject($this->bucket, $path, $this->bucket, $path, $options);
        } catch (\Exception $e) {
            throw UnableToSetVisibility::atLocation($path, $e->getMessage());
        }
    }

    public function visibility(string $path): FileAttributes
    {
        try {
            $meta = $this->client->getObjectMeta($this->bucket, $path);
            $headers = $meta['x-oss-object-acl'] ?? null;
            $visibility = ($headers === 'public-read' || $headers === 'public-read-write') ? 'public' : 'private';
            
            return new FileAttributes($path, null, $visibility);
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::visibility($path, '', $e);
        }
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $meta = $this->client->getObjectMeta($this->bucket, $path);
            $mimeType = $meta['content-type'] ?? 'application/octet-stream';
            
            return new FileAttributes($path, null, null, null, $mimeType);
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path, '', $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $meta = $this->client->getObjectMeta($this->bucket, $path);
            $lastModified = strtotime($meta['last-modified'] ?? 'now');
            
            return new FileAttributes($path, null, null, $lastModified);
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path, '', $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $meta = $this->client->getObjectMeta($this->bucket, $path);
            $fileSize = (int) ($meta['content-length'] ?? 0);
            
            return new FileAttributes($path, $fileSize);
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($path, '', $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->client->copyObject($this->bucket, $source, $this->bucket, $destination);
        } catch (\Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * 写入内容（兼容 Laravel Storage facade）
     * @param string $path 路径
     * @param string $content 内容
     * @return bool
     */
    public function put(string $path, string $content): bool
    {
        try {
            $this->write($path, $content, new Config());
            return true;
        } catch (\Exception $e) {
            logger()->error('OSS上传失败: ' . $e->getMessage(), [
                'path' => $path,
                'bucket' => $this->bucket,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 删除文件（兼容 Laravel Storage facade）
     * @param string $paths 单个路径或数组
     * @return bool
     */
    public function deleteMultiple($paths): bool
    {
        if (is_array($paths)) {
            foreach ($paths as $path) {
                $this->delete($path);
            }
            return true;
        }

        try {
            $this->delete($paths);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取文件的访问URL（兼容 Laravel Storage facade）
     * @param string $path 文件路径
     * @return string 完整的OSS访问URL
     */
    public function url(string $path): string
    {
        // 优先使用CDN域名（如果配置了的话）
        if (!empty($this->config['cdn_domain'])) {
            $scheme = !empty($this->config['ssl']) ? 'https' : 'http';
            return rtrim($this->config['cdn_domain'], '/') . '/' . ltrim($path, '/');
        }

        // 使用标准OSS Endpoint生成URL
        $endpoint = $this->config['endpoint'] ?? '';
        $bucket = $this->config['bucket'] ?? '';
        $scheme = !empty($this->config['ssl']) ? 'https' : 'http';

        // 格式：https://{bucket}.{endpoint}/{path}
        // 例如：https://my-bucket.oss-cn-hangzhou.aliyuncs.com/products/2026/05/25/image.jpg
        if ($endpoint && $bucket) {
            return sprintf('%s://%s.%s/%s', $scheme, $bucket, rtrim($endpoint, '/'), ltrim($path, '/'));
        }

        // 兜底：返回相对路径（不应该走到这里）
        return '/' . $path;
    }
}
