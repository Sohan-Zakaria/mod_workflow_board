<?php

/**
 * @package     mod_workflow_board
 * @subpackage  Joomla.Module
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\WorkflowBoard\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Extension\ModuleInterface;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\Module\WorkflowBoard\Administrator\Helper\WorkflowBoardHelper;

/**
 * Dispatcher for mod_workflow_board.
 *
 * Inheriting AbstractModuleDispatcher gives us:
 *  - Access to $this->app, $this->module, $this->params
 *  - The dispatch() method which calls getLayoutData() then renders the template.
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data array exposed to the template.
     *
     * @return  array<string, mixed>
     *
     * @since   1.0.0
     */
    protected function getLayoutData(): array
    {
        /** @var WorkflowBoardHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('WorkflowBoardHelper');
        $helper->setDatabase($this->app->getDatabase());

        $params = $this->params;
        $app    = $this->app;
        $user   = $app->getIdentity();
        $doc    = $app->getDocument();

        // ── Workflow-enabled guard ────────────────────────────────────────
        $workflowEnabled = $helper->isWorkflowEnabled();

        $stages      = [];
        $stageData   = [];
        $workflowId  = 0;

        if ($workflowEnabled) {
            $workflowId = $helper->resolveWorkflowId((int) $params->get('workflow_id', 0));
            $stages     = $helper->getStages($workflowId);

            foreach ($stages as $stage) {
                $stageData[$stage->id] = [
                    'articles'    => $helper->getArticlesForStage($stage->id, $params, $user),
                    'transitions' => [],   // populated per-article in the template
                ];
            }
        }

        // ── Register Web Assets ───────────────────────────────────────────
        $wa = $doc->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('mod_workflow_board');
        $wa->useStyle('mod_workflow_board.board')
           ->useScript('mod_workflow_board.board');

        // ── Pass configuration to JS ──────────────────────────────────────
        $doc->addScriptOptions('mod_workflow_board', [
            'ajaxUrl'      => 'index.php?option=com_ajax&module=workflow_board&method=transition&format=json',
            'token'        => \Joomla\CMS\Session\Session::getFormToken(),
            'tokenField'   => \Joomla\CMS\Session\Session::getFormToken() . '=1',
            'workflowId'   => $workflowId,
            'layoutMode'   => $params->get('layout_mode', 'kanban'),
            'enableDragDrop' => (bool) $params->get('enable_drag_drop', 1),
            'strings'      => [
                'transitioning'  => \Joomla\CMS\Language\Text::_('MOD_WORKFLOW_BOARD_JS_TRANSITIONING'),
                'success'        => \Joomla\CMS\Language\Text::_('MOD_WORKFLOW_BOARD_JS_SUCCESS'),
                'error'          => \Joomla\CMS\Language\Text::_('MOD_WORKFLOW_BOARD_JS_ERROR'),
                'checkedOut'     => \Joomla\CMS\Language\Text::_('MOD_WORKFLOW_BOARD_JS_CHECKED_OUT'),
            ],
        ]);

        return [
            'app'             => $app,
            'params'          => $params,
            'module'          => $this->module,
            'user'            => $user,
            'helper'          => $helper,
            'workflowEnabled' => $workflowEnabled,
            'workflowId'      => $workflowId,
            'stages'          => $stages,
            'stageData'       => $stageData,
            'layoutMode'      => $params->get('layout_mode', 'kanban'),
            'enableDragDrop'  => (bool) $params->get('enable_drag_drop', 1),
        ];
    }
}
