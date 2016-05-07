<?php
declare(strict_types=1);

use Aura\Di\Container as DiContainer;

final class Factory
{
    /**
     * @var DiContainer
     */
    private $diContainer;

    /**
     * @param DiContainer $diContainer
     */
    public function __construct(DiContainer $diContainer)
    {
        $this->diContainer = $diContainer;
    }

    public function create(array $params)
    {
    }
}
