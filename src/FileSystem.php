<?php
namespace liuguang\fs;

class FileSystem
{
    
    use ParamChecker;

    /**
     *
     * @var IFileSystem
     */
    private $driverObj;

    public function __construct(array $config)
    {
        $this->checkConfig($config, [
            'driver'
        ]);
        $driver = $config['driver'];
        $driverClass = __NAMESPACE__ . '\\drivers\\' . ucfirst($driver) . 'File';
        if (! class_exists($driverClass)) {
            throw new FsException('filesystem driver [' . $driver . '] not found');
        }
        unset($config['driver']);
        $this->driverObj = new $driverClass($config);
    }

    /**
     * 保存文件
     *
     * @param string $tmpPath
     *            本地临时路径
     * @param string $savePath
     *            保存路径
     * @param string $contentType
     *            文件类型
     * @return void
     * @throws FsException
     */
    public function saveFile(string $tmpPath, string $savePath, ?string $contentType = null): void
    {
        $this->driverObj->saveFile($tmpPath, $savePath, $contentType);
    }

    /**
     * 保存文件内容
     *
     * @param string $savePath
     *            保存路径
     * @param string $content
     *            文件内容
     * @param string $contentType
     *            文件类型
     * @return void
     * @throws FsException
     */
    public function writeFile(string $savePath, string $content, ?string $contentType = null): void
    {
        $this->driverObj->writeFile($savePath, $content, $contentType);
    }

    /**
     * 读取文件内容
     *
     * @param string $savePath
     *            保存路径
     * @return string
     * @throws FsException
     */
    public function readFile(string $savePath): string
    {
        return $this->driverObj->readFile($savePath);
    }

    /**
     * 删除文件
     *
     * @param string $savePath
     *            保存路径
     * @return void
     * @throws FsException
     */
    public function deleteFile(string $savePath): void
    {
        $this->driverObj->deleteFile($savePath);
    }

    /**
     * 获取文件的url地址
     *
     * @param string $savePath
     *            保存路径
     * @return string
     * @throws FsException
     */
    public function getFileUrl(string $savePath): string
    {
        return $this->driverObj->getFileUrl($savePath);
    }
}

