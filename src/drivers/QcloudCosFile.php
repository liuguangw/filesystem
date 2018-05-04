<?php
namespace liuguang\fs\drivers;

use liuguang\fs\IFileSystem;
use GuzzleHttp\Client;
use liuguang\fs\FsException;
use liuguang\fs\MimeHelper;
use Psr\Http\Message\ResponseInterface;

class QcloudCosFile implements IFileSystem
{

    private $region;

    private $bucketName;

    private $appId;

    private $secretId;

    private $secretKey;

    // 加速域名 如http://upload-1251696865.file.myqcloud.com
    private $httpContext;

    /*
     * //地区列表(参考https://cloud.tencent.com/document/product/436/6224)
     * 北京一区（华北） ap-beijing-1
     * 北京 ap-beijing
     * 上海（华东） ap-shanghai
     * 广州（华南） ap-guangzhou
     * 成都（西南） ap-chengdu
     * 重庆 ap-chongqing
     * 新加坡 ap-singapore
     * 香港 ap-hongkong
     * 多伦多 na-toronto
     * 法兰克福 eu-frankfurt
     * 孟买 ap-mumbai
     * 首尔 ap-seoul
     * 硅谷 na-siliconvalley
     * 弗吉尼亚 na-ashburn
     */
    public function __construct(array $config)
    {
        $this->region = $config['region'];
        $this->bucketName = $config['bucketName'];
        $this->appId = $config['appId'];
        $this->secretId = $config['secretId'];
        $this->secretKey = $config['secretKey'];
        $this->httpContext = $config['httpContext'];
    }

    private function getApiHost(): string
    {
        return $this->bucketName . '-' . $this->appId . '.cos.' . $this->region . '.myqcloud.com';
    }

    private function getAuthorization(string $requestMethod, string $path): string
    {
        $signTime = (time() - 60) . ';' . (time() + 600);
        $httpString = strtolower($requestMethod) . "\n" . urldecode($path) . "\n\nhost=" . $this->getApiHost() . "\n";
        $sha1edHttpString = sha1($httpString);
        $stringToSign = "sha1\n" . $signTime . "\n" . $sha1edHttpString . "\n";
        $signKey = hash_hmac('sha1', $signTime, $this->secretKey);
        $signature = hash_hmac('sha1', $stringToSign, $signKey);
        $authorization = 'q-sign-algorithm=sha1&q-ak=' . $this->secretId . '&q-sign-time=' . $signTime;
        $authorization .= ('&q-key-time=' . $signTime . '&q-header-list=host&q-url-param-list=&q-signature=' . $signature);
        return $authorization;
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
        $options = [
            'headers' => [
                'Content-Type' => $contentType,
                'x-cos-acl' => 'public-read',
                'Authorization' => $this->getAuthorization($requestMethod, $path)
            ]
        ];
        $url = 'http://' . $this->getApiHost() . $path;
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
        $options = [
            'headers' => [
                'Authorization' => $this->getAuthorization($requestMethod, $path)
            ]
        ];
        $url = 'http://' . $this->getApiHost() . $path;
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
        $options = [
            'headers' => [
                'Authorization' => $this->getAuthorization($requestMethod, $path)
            ]
        ];
        $url = 'http://' . $this->getApiHost() . $path;
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

