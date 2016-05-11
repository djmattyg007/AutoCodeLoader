<?php
declare(strict_types=1);

use Aura\Di\Container as DiContainer;

final class Proxy
{
    /**
     * @var DiContainer
     */
    private $_diContainer;

    private $_instance;

    /**
     * @param DiContainer $diContainer
     * @param bool $shared
     */
    public function __construct(DiContainer $diContainer)
    {
        $this->_diContainer = $diContainer;
    }

    private function _getInstance()
    {
        if ($this->_instance === null) {
            $this->_instance = $this->_diContainer->newInstance(_INSTANCE_NAME_);
        }
        return $this->_instance;
    }

    public function __clone()
    {
        $this->_instance = clone $this->_getInstance();
    }
}
