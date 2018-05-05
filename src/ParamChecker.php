<?php
namespace liuguang\fs;

trait ParamChecker {

    /**
     * 判断是否传入了必要参数
     *
     * @param array $config            
     * @param array $needFields            
     * @throws FsException
     */
    public function checkConfig(array &$config, array $needFields): void
    {
        foreach ($needFields as $fieldName) {
            if (! array_key_exists($fieldName, $config)) {
                throw new FsException('config:' . $fieldName . ' is not defined');
            }
        }
    }
}

