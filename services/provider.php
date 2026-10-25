<?php

/**
 * @package     mod_workflow_board
 * @subpackage  Joomla.Module
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Service provider for mod_workflow_board.
 *
 * Registers three services required by Joomla 6:
 *  1. ModuleDispatcherFactory  – resolves the Dispatcher class.
 *  2. HelperFactory            – resolves Helper classes (incl. com_ajax routing).
 *  3. Module                   – the Module extension object itself.
 */
return new class implements ServiceProviderInterface {

    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(
            new ModuleDispatcherFactory('\\Joomla\\Module\\WorkflowBoard')
        );

        $container->registerServiceProvider(
            new HelperFactory('\\Joomla\\Module\\WorkflowBoard\\Administrator\\Helper')
        );

        $container->registerServiceProvider(new Module());
    }
};
