<?php

/**
 * @package     mod_workflow_board
 * @subpackage  Joomla.Module
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\WorkflowBoard\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\Component\Workflow\Administrator\Workflow\Workflow;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Helper class for mod_workflow_board.
 *
 * Implements DatabaseAwareInterface so the DI container / Dispatcher can
 * inject the database driver via setDatabase().
 *
 * The public transitionAjax() method is routed to by com_ajax when the
 * request matches:
 *   ?option=com_ajax&module=workflow_board&method=transition&format=json
 *
 * @since  1.0.0
 */
class WorkflowBoardHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    // ──────────────────────────────────────────────────────────────────────
    //  Workflow-enabled guard
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Checks whether com_content has Workflow functionality enabled.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function isWorkflowEnabled(): bool
    {
        return (bool) ComponentHelper::getParams('com_content')->get('workflow_enabled', 0);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Workflow resolution
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Resolves a workflow ID from the module param.
     * When $paramId is 0 / empty, returns the ID of the default (lowest ordering)
     * published workflow for com_content.article.
     *
     * @param   int  $paramId  Workflow ID from module params (0 = auto).
     *
     * @return  int  Resolved workflow ID (0 if none found).
     *
     * @since   1.0.0
     */
    public function resolveWorkflowId(int $paramId): int
    {
        if ($paramId > 0) {
            return $paramId;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__workflows'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('default') . ' = 1')
            ->setLimit(1);

        $id = (int) $db->setQuery($query)->loadResult();

        if ($id === 0) {
            // Fallback: first published workflow
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__workflows'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('ordering') . ' ASC')
                ->setLimit(1);

            $id = (int) $db->setQuery($query)->loadResult();
        }

        return $id;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Stage retrieval
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Returns all published stages for the given workflow, ordered by ordering ASC.
     *
     * @param   int  $workflowId  The workflow ID.
     *
     * @return  array<int, object>  Array of stage row objects.
     *
     * @since   1.0.0
     */
    public function getStages(int $workflowId): array
    {
        if ($workflowId <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('s.id'),
                    $db->quoteName('s.title'),
                    $db->quoteName('s.description'),
                    $db->quoteName('s.ordering'),
                    $db->quoteName('s.default'),
                    $db->quoteName('w.id', 'workflow_id'),
                    $db->quoteName('w.title', 'workflow_title'),
                ]
            )
            ->from($db->quoteName('#__workflow_stages', 's'))
            ->join('INNER', $db->quoteName('#__workflows', 'w') . ' ON ' . $db->quoteName('w.id') . ' = ' . $db->quoteName('s.workflow_id'))
            ->where($db->quoteName('s.workflow_id') . ' = :workflowId')
            ->where($db->quoteName('s.published') . ' = 1')
            ->where($db->quoteName('w.published') . ' = 1')
            ->where($db->quoteName('w.extension') . ' = ' . $db->quote('com_content.article'))
            ->order($db->quoteName('s.ordering') . ' ASC')
            ->bind(':workflowId', $workflowId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObjectList('id') ?: [];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Article retrieval per stage
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Returns articles assigned to a given workflow stage, respecting all module filters.
     *
     * @param   int       $stageId  The workflow stage ID.
     * @param   Registry  $params   Module parameters.
     * @param   User      $user     The current logged-in user.
     *
     * @return  array<int, object>  Array of article row objects.
     *
     * @since   1.0.0
     */
    public function getArticlesForStage(int $stageId, Registry $params, User $user): array
    {
        $db    = $this->getDatabase();
        $limit = max(1, min(25, (int) $params->get('limit_per_stage', 5)));

        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('c.id'),
                    $db->quoteName('c.title'),
                    $db->quoteName('c.alias'),
                    $db->quoteName('c.state'),
                    $db->quoteName('c.catid'),
                    $db->quoteName('c.created_by'),
                    $db->quoteName('c.created'),
                    $db->quoteName('c.modified'),
                    $db->quoteName('c.checked_out'),
                    $db->quoteName('c.checked_out_time'),
                    $db->quoteName('cat.title', 'category_title'),
                    $db->quoteName('u.name', 'author_name'),
                    $db->quoteName('uco.name', 'editor_name'),
                    $db->quoteName('wa.stage_id'),
                ]
            )
            ->from($db->quoteName('#__workflow_associations', 'wa'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('wa.item_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__categories', 'cat') . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('c.catid')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('c.created_by')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'uco') . ' ON ' . $db->quoteName('uco.id') . ' = ' . $db->quoteName('c.checked_out')
            )
            ->where($db->quoteName('wa.stage_id') . ' = :stageId')
            ->where($db->quoteName('wa.extension') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('c.state') . ' != -2')  // exclude trashed
            ->bind(':stageId', $stageId, ParameterType::INTEGER)
            ->setLimit($limit);

        // ── Category filter ───────────────────────────────────────────────
        $catids = $params->get('catid', []);
        if (!empty($catids)) {
            if (!is_array($catids)) {
                $catids = [$catids];
            }
            $catids = array_map('intval', $catids);
            $query->whereIn($db->quoteName('c.catid'), $catids);
        }

        // ── Author filter ─────────────────────────────────────────────────
        $authorFilter = $params->get('author_filter', 'all');
        $userId       = (int) $user->id;

        if ($authorFilter === 'by_me') {
            $query->where($db->quoteName('c.created_by') . ' = :userId')
                  ->bind(':userId', $userId, ParameterType::INTEGER);
        } elseif ($authorFilter === 'not_me') {
            $query->where($db->quoteName('c.created_by') . ' != :userId')
                  ->bind(':userId', $userId, ParameterType::INTEGER);
        }

        // ── Order by ─────────────────────────────────────────────────────
        switch ($params->get('order_by', 'modified_desc')) {
            case 'modified_asc':
                $query->order($db->quoteName('c.modified') . ' ASC');
                break;
            case 'created_desc':
                $query->order($db->quoteName('c.created') . ' DESC');
                break;
            case 'created_asc':
                $query->order($db->quoteName('c.created') . ' ASC');
                break;
            case 'title_asc':
                $query->order($db->quoteName('c.title') . ' ASC');
                break;
            case 'title_desc':
                $query->order($db->quoteName('c.title') . ' DESC');
                break;
            default: // modified_desc
                $query->order($db->quoteName('c.modified') . ' DESC');
                break;
        }

        return $db->setQuery($query)->loadObjectList('id') ?: [];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Transition retrieval + ACL
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Returns the list of published transitions originating from a given stage,
     * filtered to only those the current user is authorised to execute.
     *
     * @param   int   $stageId     The source stage ID.
     * @param   int   $workflowId  The workflow ID (needed for ACL asset name).
     * @param   User  $user        The current logged-in user.
     *
     * @return  array<int, object>  Authorised transition row objects.
     *
     * @since   1.0.0
     */
    public function getTransitionsForStage(int $stageId, int $workflowId, User $user): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('t.id'),
                    $db->quoteName('t.title'),
                    $db->quoteName('t.from_stage_id'),
                    $db->quoteName('t.to_stage_id'),
                    $db->quoteName('t.description'),
                ]
            )
            ->from($db->quoteName('#__workflow_transitions', 't'))
            ->where($db->quoteName('t.published') . ' = 1')
            ->where(
                '(' . $db->quoteName('t.from_stage_id') . ' = :stageId OR ' . $db->quoteName('t.from_stage_id') . ' = -1)'
            )
            ->bind(':stageId', $stageId, ParameterType::INTEGER)
            ->order($db->quoteName('t.ordering') . ' ASC');

        $all = $db->setQuery($query)->loadObjectList() ?: [];

        // Filter by ACL
        return array_values(
            array_filter($all, static function (object $t) use ($user, $workflowId): bool {
                $asset = 'com_content.workflow.' . $workflowId . '.transition.' . $t->id;
                return $user->authorise('core.execute.transition', $asset)
                    || $user->authorise('core.admin', 'com_content');
            })
        );
    }

    /**
     * Determines whether the current user may act on a specific article
     * (considering core.edit, core.edit.own, and check-out status).
     *
     * @param   object  $article  The article row object.
     * @param   User    $user     The current logged-in user.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function canEditArticle(object $article, User $user): bool
    {
        // Checked out by someone else?
        if (!empty($article->checked_out) && (int) $article->checked_out !== (int) $user->id) {
            return false;
        }

        if ($user->authorise('core.edit', 'com_content.article.' . $article->id)) {
            return true;
        }

        if ($user->authorise('core.edit.own', 'com_content.article.' . $article->id)
            && (int) $article->created_by === (int) $user->id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Returns a human-readable relative date string (e.g. "3 days ago").
     *
     * @param   string  $dateStr  Date string from DB.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function relativeDate(string $dateStr): string
    {
        try {
            $date = \Joomla\CMS\Date\Date::getInstance($dateStr);
            $now  = \Joomla\CMS\Date\Date::getInstance();
            $diff = $now->toUnix() - $date->toUnix();

            if ($diff < 60) {
                return Text::_('JLIB_HTML_DATE_RELATIVE_LESSTHANAMINUTE');
            }
            if ($diff < 3600) {
                $mins = (int) round($diff / 60);
                return Text::plural('JLIB_HTML_DATE_RELATIVE_MINUTES', $mins);
            }
            if ($diff < 86400) {
                $hours = (int) round($diff / 3600);
                return Text::plural('JLIB_HTML_DATE_RELATIVE_HOURS', $hours);
            }
            $days = (int) round($diff / 86400);
            if ($days <= 30) {
                return Text::plural('JLIB_HTML_DATE_RELATIVE_DAYS', $days);
            }

            return $date->format(Text::_('DATE_FORMAT_LC4'), true);
        } catch (\Exception $e) {
            return $dateStr;
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  com_ajax handler — transitionAjax()
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Handles com_ajax AJAX requests to execute a workflow transition.
     *
     * Route:
     *   POST index.php?option=com_ajax&module=workflow_board&method=transition&format=json
     *
     * POST body (application/x-www-form-urlencoded):
     *   - {token}=1        (CSRF token)
     *   - item_id          (int)  Article ID
     *   - transition_id    (int)  Transition ID to execute
     *
     * @return  array<string, mixed>  JSON-serialisable response payload.
     *
     * @throws  \RuntimeException  On any validation or execution failure.
     *
     * @since   1.0.0
     */
    public function transitionAjax(): array
    {
        /** @var CMSApplicationInterface $app */
        $app   = Factory::getApplication();
        $input = $app->getInput();

        // ── 1. CSRF validation ────────────────────────────────────────────
        if (!Session::checkToken('request')) {
            http_response_code(403);
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        // ── 2. Input validation ───────────────────────────────────────────
        $itemId       = $input->getInt('item_id', 0);
        $transitionId = $input->getInt('transition_id', 0);

        if ($itemId <= 0 || $transitionId <= 0) {
            http_response_code(400);
            throw new \RuntimeException(Text::_('MOD_WORKFLOW_BOARD_ERROR_INVALID_INPUT'), 400);
        }

        // ── 3. Load article & check permissions ──────────────────────────
        $user = $app->getIdentity();
        $db   = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('c.id'),
                    $db->quoteName('c.title'),
                    $db->quoteName('c.state'),
                    $db->quoteName('c.created_by'),
                    $db->quoteName('c.checked_out'),
                    $db->quoteName('wa.stage_id'),
                ]
            )
            ->from($db->quoteName('#__content', 'c'))
            ->join(
                'INNER',
                $db->quoteName('#__workflow_associations', 'wa')
                . ' ON ' . $db->quoteName('wa.item_id') . ' = ' . $db->quoteName('c.id')
                . ' AND ' . $db->quoteName('wa.extension') . ' = ' . $db->quote('com_content.article')
            )
            ->where($db->quoteName('c.id') . ' = :itemId')
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $article = $db->setQuery($query)->loadObject();

        if (!$article) {
            http_response_code(404);
            throw new \RuntimeException(Text::_('MOD_WORKFLOW_BOARD_ERROR_ARTICLE_NOT_FOUND'), 404);
        }

        if (!$this->canEditArticle($article, $user)) {
            http_response_code(403);
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        // ── 4. Validate transition is published & user is authorised ──────
        $tQuery = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('t.id'),
                    $db->quoteName('t.title'),
                    $db->quoteName('t.from_stage_id'),
                    $db->quoteName('t.to_stage_id'),
                    $db->quoteName('t.workflow_id'),
                ]
            )
            ->from($db->quoteName('#__workflow_transitions', 't'))
            ->where($db->quoteName('t.id') . ' = :transId')
            ->where($db->quoteName('t.published') . ' = 1')
            ->bind(':transId', $transitionId, ParameterType::INTEGER);

        $transition = $db->setQuery($tQuery)->loadObject();

        if (!$transition) {
            http_response_code(404);
            throw new \RuntimeException(Text::_('MOD_WORKFLOW_BOARD_ERROR_TRANSITION_NOT_FOUND'), 404);
        }

        // Verify transition is valid from this article's current stage
        $currentStageId = (int) $article->stage_id;
        if ($transition->from_stage_id != -1 && (int) $transition->from_stage_id !== $currentStageId) {
            http_response_code(400);
            throw new \RuntimeException(Text::_('MOD_WORKFLOW_BOARD_ERROR_TRANSITION_INVALID_FROM'), 400);
        }

        // ACL: user must be authorised to execute this transition
        $workflowId = (int) $transition->workflow_id;
        $asset      = 'com_content.workflow.' . $workflowId . '.transition.' . $transitionId;

        if (!$user->authorise('core.execute.transition', $asset) && !$user->authorise('core.admin', 'com_content')) {
            http_response_code(403);
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        // ── 5. Execute the transition via the core Workflow class ─────────
        // This fires all workflow plugins (state changes, emails, etc.)
        try {
            $workflow = new Workflow(['extension' => 'com_content.article']);
            $result   = $workflow->executeTransition([$itemId], $transitionId);
        } catch (\Exception $e) {
            http_response_code(500);
            throw new \RuntimeException(
                Text::sprintf('MOD_WORKFLOW_BOARD_ERROR_TRANSITION_FAILED', $e->getMessage()),
                500
            );
        }

        if (!$result) {
            http_response_code(500);
            throw new \RuntimeException(Text::_('MOD_WORKFLOW_BOARD_ERROR_TRANSITION_FAILED_GENERIC'), 500);
        }

        // ── 6. Fetch the updated article state & new stage ────────────────
        $updatedQuery = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('c.id'),
                    $db->quoteName('c.state'),
                    $db->quoteName('wa.stage_id'),
                    $db->quoteName('s.title', 'stage_title'),
                ]
            )
            ->from($db->quoteName('#__content', 'c'))
            ->join(
                'INNER',
                $db->quoteName('#__workflow_associations', 'wa')
                . ' ON ' . $db->quoteName('wa.item_id') . ' = ' . $db->quoteName('c.id')
                . ' AND ' . $db->quoteName('wa.extension') . ' = ' . $db->quote('com_content.article')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__workflow_stages', 's') . ' ON ' . $db->quoteName('s.id') . ' = ' . $db->quoteName('wa.stage_id')
            )
            ->where($db->quoteName('c.id') . ' = :itemId')
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $updated = $db->setQuery($updatedQuery)->loadObject();

        $newStageId = $updated ? (int) $updated->stage_id : (int) $transition->to_stage_id;
        $newState   = $updated ? (int) $updated->state : 0;

        // Fetch new valid transitions for the updated stage
        $newTransitions = $this->getTransitionsForStage($newStageId, $workflowId, $user);

        return [
            'success'        => true,
            'message'        => Text::_('MOD_WORKFLOW_BOARD_JS_SUCCESS'),
            'item_id'        => $itemId,
            'from_stage_id'  => $currentStageId,
            'to_stage_id'    => $newStageId,
            'stage_title'    => $updated ? htmlspecialchars($updated->stage_title, ENT_QUOTES, 'UTF-8') : '',
            'article_state'  => $newState,
            'state_badge'    => $this->renderStateBadge($newState),
            'transitions'    => array_map(static fn($t) => [
                'id'    => (int) $t->id,
                'title' => htmlspecialchars(Text::_($t->title), ENT_QUOTES, 'UTF-8'),
                'to'    => (int) $t->to_stage_id,
            ], $newTransitions),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Returns a Bootstrap 5 state badge HTML string for an article state value.
     *
     * @param   int  $state  Joomla article state (1=Published, 0=Unpublished, 2=Archived, -2=Trashed).
     *
     * @return  string  HTML badge.
     *
     * @since   1.0.0
     */
    public function renderStateBadge(int $state): string
    {
        $map = [
            1  => ['bg-success',   'JPUBLISHED'],
            0  => ['bg-secondary', 'JUNPUBLISHED'],
            2  => ['bg-info',      'JARCHIVED'],
            -2 => ['bg-danger',    'JTRASHED'],
        ];

        [$class, $key] = $map[$state] ?? ['bg-secondary', 'JUNKNOWN'];
        $label = htmlspecialchars(Text::_($key), ENT_QUOTES, 'UTF-8');

        return '<span class="badge ' . $class . '">' . $label . '</span>';
    }
}
