<?php
namespace liuguang\fs\drivers;

use liuguang\fs\IFileSystem;
use GuzzleHttp\Client;
use liuguang\fs\MimeHelper;
use liuguang\fs\FsException;
use Psr\Http\Message\ResponseInterface;
use liuguang\fs\ParamChecker;

class AliyunOssFile implements IFileSystem
{
    use ParamChecker;

    private $bucketName;

    private $accessKeyId;

    private $accessKeySecret;

    // 外网访问地址 如http://upload-1251696865.file.myqcloud.com
    private $httpContext;

    // api地址(内网免流)
    private $apiHttpContext;

    public function __construct(array $config)
    {
        $this->checkConfig($config, [
            'bucketName',
            'accessKeyId',
            'accessKeySecret',
            'httpContext',
            'apiHttpContext'
        ]);
        $this->bucketName = $config['bucketName'];
        $this->accessKeyId = $config['accessKeyId'];
        $this->accessKeySecret = $config['accessKeySecret'];
        $this->httpContext = $config['httpContext'];
        $this->apiHttpContext = $config['apiHttpContext'];
    }

    private function getAuthorization(string $requestMethod, string $objectName, string $date, string $contentType = '', array $ossHeaders = [], string $query = ''): string
    {
        $stringToSign = $requestMethod . "\n\n" . $contentType . "\n" . $date . "\n";
        $ossHeadersCount = count($ossHeaders);
        $headerStr = '';
        foreach ($ossHeaders as $k => $v) {
            $headerStr .= (strtolower($k) . ':' . $v . "\n");
        }
        $stringToSign .= ($headerStr . '/' . $this->bucketName . '/' . $objectName);
        if ($query != '') {
            $stringToSign .= ('?' . $query);
        }
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        return 'OSS ' . $this->accessKeyId . ':' . $signature;
    }

    private function getClient(): Client
    {
        return new Client([
            'http_errors' => false
        ]);
    }

    /**
     * 抛出异常
     *
     * @param ResponseInterface $response            
     * @throws FsException
     */
    private function throwFsException(ResponseInterface $response)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($response->getBody()
            ->getContents());
        $code = $doc->getElementsByTagName('Code')->item(0)->nodeValue;
        $message = $doc->getElementsByTagName('Message')->item(0)->nodeValue;
        throw new FsException('[' . $code . ']' . $message);
    }

    /**
     * 处理上传
     *
     * @param array $option            
     * @return void
     * @throws FsException
     */
    private function doUploadFile(array $fileOption, string $savePath, string $contentType = null): void
    {
        if ($contentType === null) {
            $contentType = MimeHelper::getMimetype($savePath);
        }
        $path = '/' . $savePath;
        $requestMethod = 'PUT';
        $date = gmdate('D, d M Y H:i:s T');
        $ossHeaders = [
            'x-oss-object-acl' => 'public-read'
        ];
        $httpHeaders = array_merge([
            'Content-Type' => $contentType,
            'Date' => $date
        ], $ossHeaders);
        $httpHeaders['Authorization'] = $this->getAuthorization($requestMethod, $savePath, $date, $contentType, $ossHeaders);
        $options = [
            'headers' => $httpHeaders
        ];
        $url = $this->apiHttpContext . $path;
        $response = $this->getClient()->request($requestMethod, $url, array_merge($fileOption, $options));
        if ($response->getStatusCode() != 200) {
            $this->throwFsException($response);
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
        $resource = fopen($tmpPath, 'r');
        $this->doUploadFile([
            'body' => $resource
        ], $savePath, $contentType);
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
            'body' => $content
        ], $savePath, $contentType);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::readFile()
     */
    public function readFile(string $savePath): string
    {
        $path = '/' . $savePath;
        $requestMethod = 'GET';
        $date = gmdate('D, d M Y H:i:s T');
        $httpHeaders = [
            'Date' => $date,
            'Authorization' => $this->getAuthorization($requestMethod, $savePath, $date)
        ];
        $options = [
            'headers' => $httpHeaders
        ];
        $url = $this->apiHttpContext . $path;
        $response = $this->getClient()->request($requestMethod, $url, $options);
        if ($response->getStatusCode() != 200) {
            $this->throwFsException($response);
        }
        return $response->getBody()->getContents();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::deleteFile()
     */
    public function deleteFile(string $savePath): void
    {
        $path = '/' . $savePath;
        $requestMethod = 'DELETE';
        $date = gmdate('D, d M Y H:i:s T');
        $httpHeaders = [
            'Date' => $date,
            'Authorization' => $this->getAuthorization($requestMethod, $savePath, $date)
        ];
        $options = [
            'headers' => $httpHeaders
        ];
        $url = $this->apiHttpContext . $path;
        $response = $this->getClient()->request($requestMethod, $url, $options);
        if ($response->getStatusCode() != 204) {
            $this->throwFsException($response);
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

