<?php
namespace liuguang\fs\drivers;

use liuguang\fs\IFileSystem;
use liuguang\fs\FsException;
use liuguang\fs\ParamChecker;

/**
 * 本地文件存储
 *
 * @author liuguang
 *        
 */
class LocalFile implements IFileSystem
{
    use ParamChecker;

    private $saveDir;

    private $httpContext = null;

    public function __construct(array $config)
    {
        $this->checkConfig($config, [
            'saveDir'
        ]);
        $this->saveDir = $config['saveDir'];
        if (isset($config['httpContext'])) {
            $this->httpContext = $config['httpContext'];
        }
    }

    /**
     * 构建目标目录
     *
     * @param string $distPath
     *            目标文件路径
     * @return void
     */
    private function buildDistDir(string $distPath): void
    {
        $distDir = dirname($distPath);
        if (! is_dir($distDir)) {
            if (@mkdir($distDir, 0755, true) === false) {
                throw new FsException('创建目录' . $distDir . '失败');
            }
        }
    }

    /**
     * 获取目标文件保存路径
     *
     * @param string $savePath            
     * @return string
     */
    private function getDistPath(string $savePath): string
    {
        return $this->saveDir . '/./' . $savePath;
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
        $distPath = $this->getDistPath($savePath);
        $this->buildDistDir($distPath);
        if (@copy($tmpPath, $distPath) === false) {
            throw new FsException('copy文件到' . $distPath . '失败');
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::writeFile()
     */
    public function writeFile(string $savePath, string $content, string $contentType = null): void
    {
        $distPath = $this->getDistPath($savePath);
        $this->buildDistDir($distPath);
        if (@file_put_contents($distPath, $content) === false) {
            throw new FsException('写入文件到' . $distPath . '失败');
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::readFile()
     */
    public function readFile(string $savePath): string
    {
        $distPath = $this->getDistPath($savePath);
        if (! is_file($distPath)) {
            throw new FsException('目标文件路径不存在');
        }
        $content = @file_get_contents($distPath);
        if ($content === false) {
            throw new FsException('读取文件' . $distPath . '失败');
        }
        return $content;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::deleteFile()
     */
    public function deleteFile(string $savePath): void
    {
        $distPath = $this->getDistPath($savePath);
        if (! is_file($distPath)) {
            throw new FsException('目标文件路径不存在');
        }
        $content = @file_get_contents($distPath);
        if ($content === false) {
            throw new FsException('读取文件' . $distPath . '失败');
        }
        return $content;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \liuguang\fs\IFileSystem::getFileUrl()
     */
    public function getFileUrl(string $savePath): string
    {
        if ($this->httpContext === null) {
            throw new \Exception('不支持获取URL');
        }
        return $this->httpContext . '/' . $savePath;
    }
}

