<?php
namespace liuguang\fs;

interface IFileSystem
{

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
    public function saveFile(string $tmpPath, string $savePath, ?string $contentType = null): void;

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
    public function writeFile(string $savePath, string $content, ?string $contentType = null): void;

    /**
     * 读取文件内容
     *
     * @param string $savePath
     *            保存路径
     * @return string
     * @throws FsException
     */
    public function readFile(string $savePath): string;

    /**
     * 删除文件
     *
     * @param string $savePath
     *            保存路径
     * @return void
     * @throws FsException
     */
    public function deleteFile(string $savePath): void;

    /**
     * 获取文件的url地址
     *
     * @param string $savePath
     *            保存路径
     * @return string
     * @throws FsException
     */
    public function getFileUrl(string $savePath): string;
}

