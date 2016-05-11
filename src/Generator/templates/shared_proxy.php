<?php
declare(strict_types=1);

use Aura\Di\Container as DiContainer;

final class SharedProxy
{
    /**
     * @var DiContainer
     */
    private $_diContainer;

    /**
     * @var string
     */
    private $_instanceName;

    private $_instance;

    /**
     * @param DiContainer $diContainer
     * @param string $instanceName
     */
    public function __construct(DiContainer $diContainer, string $instanceName)
    {
        $this->_diContainer = $diContainer;
        $this->_instanceName = $instanceName;
    }

    private function _getInstance()
    {
        if ($this->_instance === null) {
            $this->_instance = $this->_diContainer->get($this->_instanceName);
        }
        return $this->_instance;
    }

    public function __clone()
    {
        $this->_instance = clone $this->_getInstance();
    }
}
