<?php
namespace liuguang\fs\drivers;

use liuguang\fs\IFileSystem;
use liuguang\fs\FsException;
use GuzzleHttp\Client;
use liuguang\fs\MimeHelper;
use liuguang\fs\ParamChecker;

class QiniuFile implements IFileSystem
{
    use ParamChecker;

    private $region;

    private $bucketName;

    private $accessKey;

    private $secretKey;

    // 如http://78re52.com1.z0.glb.clouddn.com
    private $httpContext;

    private $uploadRegionArray = [
        // 华东
        'z0' => 'http://up.qiniup.com',
        // 华北
        'z1' => 'http://up-z1.qiniup.com',
        // 华南
        'z2' => 'http://up-z2.qiniup.com',
        // 北美
        'na0' => 'http://up-na0.qiniup.com',
        // 东南亚
        'as0' => 'http://up-as0.qiniup.com'
    ];

    private $rsApiUrl = 'http://rs.qiniu.com';

    public function __construct(array $config)
    {
        $this->checkConfig($config, [
            'region',
            'bucketName',
            'accessKey',
            'secretKey',
            'httpContext'
        ]);
        $this->region = $config['region'];
        $this->bucketName = $config['bucketName'];
        $this->accessKey = $config['accessKey'];
        $this->secretKey = $config['secretKey'];
        $this->httpContext = $config['httpContext'];
    }

    /**
     * 获取上传凭证
     *
     * @return string
     */
    private function getUploadToken(): string
    {
        $policyInfo = [
            'scope' => $this->bucketName,
            'deadline' => time() + 600,
            'returnBody' => json_encode([
                'name' => '$(fname)',
                'size' => '$(fsize)',
                'hash' => '$(etag)'
            ])
        ];
        $encodedPolicy = $this->getEncodedPolicy($policyInfo);
        $encodedSign = $this->getEncodedSign($encodedPolicy);
        return $this->accessKey . ':' . $encodedSign . ':' . $encodedPolicy;
    }

    private function getResUrl(string $action, string $objectName, string $query = '')
    {
        $encodedEntryURI = $this->safeBase64($this->bucketName . ':' . $objectName);
        $resUrl = '/' . $action . '/' . $encodedEntryURI;
        if (! empty($query)) {
            $resUrl .= ('?' . $query);
        }
        return $resUrl;
    }

    private function getAdminToken(string $resUrl): string
    {
        $signingStr = $resUrl . "\n";
        $encodedSign = $this->getEncodedSign($signingStr);
        return $this->accessKey . ':' . $encodedSign;
    }

    private function safeBase64(string $str): string
    {
        return str_replace([
            '+',
            '/'
        ], [
            '-',
            '_'
        ], base64_encode($str));
    }

    private function getEncodedPolicy(array $policyInfo): string
    {
        $policyStr = json_encode($policyInfo);
        return $this->safeBase64($policyStr);
    }

    private function getEncodedSign(string $encodedPolicy): string
    {
        return $this->safeBase64(hash_hmac('sha1', $encodedPolicy, $this->secretKey, true));
    }

    private function getClient(): Client
    {
        return new Client([
            'http_errors' => false
        ]);
    }

    /**
     * 处理上传
     *
     * @param array $option            
     * @return void
     * @throws FsException
     */
    private function doUploadFile(array $fileOption, string $savePath): void
    {
        if ($fileOption['headers']['Content-Type'] === null) {
            $fileOption['headers']['Content-Type'] = MimeHelper::getMimetype($savePath);
        }
        $options = [
            [
                'name' => 'key',
                'contents' => $savePath
            ],
            [
                'name' => 'token',
                'contents' => $this->getUploadToken()
            ],
            $fileOption
        ];
        $response = $this->getClient()->request('POST', $this->uploadRegionArray[$this->region], [
            'multipart' => $options
        ]);
        if ($response->getStatusCode() != 200) {
            $responseInfo = json_decode($response->getBody()->getContents(), true);
            throw new FsException($responseInfo['error']);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::saveFile()
     */
    public function saveFile(string $tmpPath, string $savePath, string $contentType = null): void
    {
        if (! is_file($tmpPath)) {
            throw new FsException('文件' . $tmpPath . '不存在');
        }
        $this->doUploadFile([
            'name' => 'file',
            'contents' => fopen($tmpPath, 'r'),
            'filename' => basename($savePath),
            'headers' => [
                'Content-Type' => $contentType
            ]
        ], $savePath);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::writeFile()
     */
    public function writeFile(string $savePath, string $content, string $contentType = null): void
    {
        $this->doUploadFile([
            'name' => 'file',
            'contents' => $content,
            'filename' => basename($savePath),
            'headers' => [
                'Content-Type' => $contentType
            ]
        ], $savePath);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::readFile()
     */
    public function readFile(string $savePath): string
    {
        $downloadUrl = $this->httpContext . '/' . $savePath . '?e=' . (time() + 600);
        $encodedSign = $this->getEncodedSign($downloadUrl);
        $token = $this->accessKey . ':' . $encodedSign;
        $downloadUrl .= ('&token=' . $token);
        $response = $this->getClient()->get($downloadUrl);
        $responseContent = $response->getBody()->getContents();
        if ($response->getStatusCode() != 200) {
            $responseInfo = json_decode($responseContent, true);
            throw new FsException($responseInfo['error']);
        }
        return $responseContent;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::deleteFile()
     */
    public function deleteFile(string $savePath): void
    {
        $resUrl = $this->getResUrl('delete', $savePath);
        $authorization = $this->getAdminToken($resUrl);
        $options = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'QBox ' . $authorization
            ]
        ];
        $response = $this->getClient()->post($this->rsApiUrl . $resUrl, $options);
        if ($response->getStatusCode() != 200) {
            $responseInfo = json_decode($response->getBody()->getContents(), true);
            throw new FsException($responseInfo['error']);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::getFileUrl()
     */
    public function getFileUrl(string $savePath): string
    {
        return $this->httpContext . '/' . $savePath;
    }
}

