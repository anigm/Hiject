<?php

/*
 * This file is part of Hiject.
 *
 * Copyright (C) 2016 Hiject Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hiject\Core\Controller;

use Hiject\Controller\AppController;
use Hiject\Core\Base;
use Hiject\Middleware\ApplicationAuthorizationMiddleware;
use Hiject\Middleware\AuthenticationMiddleware;
use Hiject\Middleware\BootstrapMiddleware;
use Hiject\Middleware\PostAuthenticationMiddleware;
use Hiject\Middleware\ProjectAuthorizationMiddleware;
use RuntimeException;

/**
 * Class Runner
 */
class Runner extends Base
{
    /**
     * Execute middleware and controller
     */
    public function execute()
    {
        try {
            $this->executeMiddleware();

            if (!$this->response->isResponseAlreadySent()) {
                $this->executeController();
            }
        } catch (PageNotFoundException $e) {
            $controllerObject = new AppController($this->container);
            $controllerObject->notFound($e->hasLayout());
        } catch (AccessForbiddenException $e) {
            $controllerObject = new AppController($this->container);
            $controllerObject->accessForbidden($e->hasLayout(), $e->getMessage());
        }
    }

    /**
     * Execute all middleware
     */
    protected function executeMiddleware()
    {
        if (DEBUG) {
            $this->logger->debug(__METHOD__);
        }

        $bootstrapMiddleware = new BootstrapMiddleware($this->container);
        $authenticationMiddleware = new AuthenticationMiddleware($this->container);
        $postAuthenticationMiddleware = new PostAuthenticationMiddleware($this->container);
        $appAuthorizationMiddleware = new ApplicationAuthorizationMiddleware($this->container);
        $projectAuthorizationMiddleware = new ProjectAuthorizationMiddleware($this->container);

        $bootstrapMiddleware->setNextMiddleware($authenticationMiddleware);
        $authenticationMiddleware->setNextMiddleware($postAuthenticationMiddleware);
        $postAuthenticationMiddleware->setNextMiddleware($appAuthorizationMiddleware);
        $appAuthorizationMiddleware->setNextMiddleware($projectAuthorizationMiddleware);

        $bootstrapMiddleware->execute();
    }

    /**
     * Execute the controller
     */
    protected function executeController()
    {
        $className = $this->getControllerClassName();

        if (DEBUG) {
            $this->logger->debug(__METHOD__.' => '.$className.'::'.$this->router->getAction());
        }

        $controllerObject = new $className($this->container);
        $controllerObject->{$this->router->getAction()}();
    }

    /**
     * Get controller class name
     *
     * @access protected
     * @return string
     * @throws RuntimeException
     */
    protected function getControllerClassName()
    {
        if ($this->router->getPlugin() !== '') {
            $className = '\Hiject\Plugin\\'.$this->router->getPlugin().'\Controller\\'.$this->router->getController();
        } else {
            $className = '\Hiject\Controller\\'.$this->router->getController();
        }

        if (! class_exists($className)) {
            throw new RuntimeException('Controller not found');
        }

        if (! method_exists($className, $this->router->getAction())) {
            throw new RuntimeException('Action not implemented');
        }

        return $className;
    }
}
