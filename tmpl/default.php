<?php

/**
 * @package     mod_workflow_board
 * @subpackage  Joomla.Module
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @var  \Joomla\CMS\Application\CMSApplicationInterface                             $app
 * @var  \Joomla\Registry\Registry                                                   $params
 * @var  \Joomla\CMS\Object\CMSObject                                                $module
 * @var  \Joomla\CMS\User\User                                                       $user
 * @var  \Joomla\Module\WorkflowBoard\Administrator\Helper\WorkflowBoardHelper       $helper
 * @var  bool                                                                        $workflowEnabled
 * @var  int                                                                         $workflowId
 * @var  array<int, object>                                                          $stages
 * @var  array<int, array>                                                           $stageData
 * @var  string                                                                      $layoutMode
 * @var  bool                                                                        $enableDragDrop
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('bootstrap.tooltip', '.wfb-tooltip');

$moduleId  = 'mod-workflow-board-' . $module->id;
$sfx       = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');

// Map article state ints → icon + label
$stateMap = [
    1  => ['icon' => 'icon-check-circle text-success',  'key' => 'JPUBLISHED'],
    0  => ['icon' => 'icon-times-circle text-secondary', 'key' => 'JUNPUBLISHED'],
    2  => ['icon' => 'icon-archive text-info',           'key' => 'JARCHIVED'],
    -2 => ['icon' => 'icon-trash text-danger',           'key' => 'JTRASHED'],
];
?>
<div id="<?php echo $moduleId; ?>" class="mod-workflow-board<?php echo $sfx ? ' ' . $sfx : ''; ?>"
     data-module-id="<?php echo (int) $module->id; ?>"
     data-workflow-id="<?php echo $workflowId; ?>"
     data-layout="<?php echo htmlspecialchars($layoutMode, ENT_QUOTES, 'UTF-8'); ?>"
     data-drag-drop="<?php echo $enableDragDrop ? '1' : '0'; ?>">

<?php if (!$workflowEnabled) : ?>
    <!-- ── Workflow disabled notice ─────────────────────────────────── -->
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <span class="icon-exclamation-triangle fs-4" aria-hidden="true"></span>
        <div>
            <strong><?php echo Text::_('MOD_WORKFLOW_BOARD_WORKFLOW_DISABLED_TITLE'); ?></strong>
            <?php echo Text::_('MOD_WORKFLOW_BOARD_WORKFLOW_DISABLED_MSG'); ?>
            <a href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_content'); ?>"
               class="alert-link ms-1">
                <?php echo Text::_('MOD_WORKFLOW_BOARD_WORKFLOW_DISABLED_LINK'); ?>
            </a>
        </div>
    </div>

<?php elseif (empty($stages)) : ?>
    <!-- ── No stages found ──────────────────────────────────────────── -->
    <div class="alert alert-info" role="alert">
        <?php echo Text::_('MOD_WORKFLOW_BOARD_NO_STAGES'); ?>
    </div>

<?php else : ?>

    <?php if ($layoutMode === 'kanban') : ?>
    <!-- ══════════════════════════════════════════════════════════════════
         KANBAN LAYOUT
    ══════════════════════════════════════════════════════════════════ -->
    <div class="wfb-kanban-wrapper">
        <div class="wfb-kanban-scroll">
            <div class="wfb-kanban-board">

            <?php foreach ($stages as $stage) :
                $articles    = $stageData[$stage->id]['articles'] ?? [];
                $transitions = $helper->getTransitionsForStage((int) $stage->id, $workflowId, $user);
                $count       = count($articles);
                $stageTitle  = Text::_($stage->title);
                $stageDesc   = !empty($stage->description) ? Text::_($stage->description) : '';
            ?>
                <div class="wfb-column"
                     data-stage-id="<?php echo (int) $stage->id; ?>"
                     id="wfb-col-<?php echo (int) $stage->id; ?>">

                    <!-- Column header -->
                    <div class="wfb-col-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="wfb-col-title mb-0">
                                <?php if ($stage->default) : ?>
                                    <span class="icon-check-circle text-success me-1 wfb-tooltip"
                                          title="<?php echo Text::_('MOD_WORKFLOW_BOARD_STAGE_DEFAULT_TIP'); ?>"
                                          aria-hidden="true"></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($stageTitle, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($stageDesc) : ?>
                                    <span class="icon-question-circle ms-1 wfb-tooltip text-muted"
                                          title="<?php echo htmlspecialchars($stageDesc, ENT_QUOTES, 'UTF-8'); ?>"
                                          aria-hidden="true"></span>
                                <?php endif; ?>
                            </h6>
                            <span class="badge bg-secondary wfb-count-badge" data-stage-id="<?php echo (int) $stage->id; ?>">
                                <?php echo $count; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Article cards -->
                    <div class="wfb-card-list"
                         data-stage-id="<?php echo (int) $stage->id; ?>"
                         <?php if ($enableDragDrop) : ?>data-drag-target="true"<?php endif; ?>>

                    <?php if (empty($articles)) : ?>
                        <div class="wfb-empty-col">
                            <span class="icon-inbox text-muted"></span>
                            <span class="visually-hidden"><?php echo Text::_('MOD_WORKFLOW_BOARD_NO_ARTICLES'); ?></span>
                        </div>
                    <?php else : ?>

                        <?php foreach ($articles as $article) :
                            $canEdit      = $helper->canEditArticle($article, $user);
                            $isCheckedOut = !empty($article->checked_out) && (int) $article->checked_out !== (int) $user->id;
                            $articleTrans = $canEdit
                                ? array_values(array_filter($transitions, static fn($t) => $t->from_stage_id == -1 || (int) $t->from_stage_id === (int) $stage->id))
                                : [];
                            $stateInfo    = $stateMap[$article->state] ?? $stateMap[0];
                            $editUrl      = Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $article->id);
                            $relDate      = $helper->relativeDate($article->modified ?: $article->created);
                        ?>
                        <div class="wfb-card<?php echo $isCheckedOut ? ' wfb-card--locked' : ''; ?><?php echo !$canEdit ? ' wfb-card--readonly' : ''; ?>"
                             data-article-id="<?php echo (int) $article->id; ?>"
                             data-stage-id="<?php echo (int) $stage->id; ?>"
                             <?php if ($enableDragDrop && $canEdit && !$isCheckedOut) : ?>draggable="true"<?php endif; ?>>

                            <!-- Card header: title + lock -->
                            <div class="wfb-card-header">
                                <?php if ($isCheckedOut) : ?>
                                    <span class="icon-lock wfb-tooltip text-warning me-1"
                                          title="<?php echo Text::sprintf('MOD_WORKFLOW_BOARD_CHECKED_OUT_BY', htmlspecialchars($article->editor_name ?? '', ENT_QUOTES, 'UTF-8')); ?>"
                                          aria-hidden="true"></span>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="wfb-card-title"
                                   title="<?php echo htmlspecialchars($article->title, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($article->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>

                            <!-- Card meta -->
                            <div class="wfb-card-meta">
                                <span class="badge bg-light text-dark border wfb-cat-badge">
                                    <span class="icon-folder" aria-hidden="true"></span>
                                    <?php echo htmlspecialchars($article->category_title ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="wfb-author">
                                    <span class="icon-user" aria-hidden="true"></span>
                                    <?php echo htmlspecialchars($article->author_name ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="wfb-date wfb-tooltip"
                                      title="<?php echo htmlspecialchars($article->modified ?: $article->created, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="icon-clock" aria-hidden="true"></span>
                                    <?php echo htmlspecialchars($relDate, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>

                            <!-- Card footer: state badge + transition actions -->
                            <div class="wfb-card-footer">
                                <span class="wfb-state-badge" data-article-id="<?php echo (int) $article->id; ?>">
                                    <?php echo $helper->renderStateBadge((int) $article->state); ?>
                                </span>

                                <?php if ($canEdit && !empty($articleTrans)) : ?>
                                <div class="dropdown wfb-transition-menu">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            aria-label="<?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?>">
                                        <span class="icon-exchange" aria-hidden="true"></span>
                                        <span class="visually-hidden"><?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end wfb-transition-list"
                                        data-article-id="<?php echo (int) $article->id; ?>"
                                        data-stage-id="<?php echo (int) $stage->id; ?>">
                                        <?php foreach ($articleTrans as $trans) : ?>
                                        <li>
                                            <button class="dropdown-item wfb-transition-btn"
                                                    type="button"
                                                    data-article-id="<?php echo (int) $article->id; ?>"
                                                    data-transition-id="<?php echo (int) $trans->id; ?>"
                                                    data-to-stage="<?php echo (int) $trans->to_stage_id; ?>">
                                                <span class="icon-arrow-right me-1" aria-hidden="true"></span>
                                                <?php echo htmlspecialchars(Text::_($trans->title), ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php elseif ($isCheckedOut) : ?>
                                <span class="badge bg-warning text-dark">
                                    <span class="icon-lock me-1" aria-hidden="true"></span>
                                    <?php echo Text::_('JLIB_HTML_CHECKED_OUT'); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
                    </div><!-- /.wfb-card-list -->
                </div><!-- /.wfb-column -->
            <?php endforeach; ?>

            </div><!-- /.wfb-kanban-board -->
        </div><!-- /.wfb-kanban-scroll -->
    </div><!-- /.wfb-kanban-wrapper -->

    <?php else : ?>
    <!-- ══════════════════════════════════════════════════════════════════
         TABBED SUMMARY LAYOUT
    ══════════════════════════════════════════════════════════════════ -->
    <?php
        $tabId  = 'wfb-tabs-' . (int) $module->id;
        $first  = true;
    ?>
    <ul class="nav nav-tabs wfb-tab-nav" id="<?php echo $tabId; ?>" role="tablist">
        <?php foreach ($stages as $stage) :
            $stageTitle = htmlspecialchars(Text::_($stage->title), ENT_QUOTES, 'UTF-8');
            $count      = count($stageData[$stage->id]['articles'] ?? []);
            $tabSlug    = 'wfb-tab-' . (int) $module->id . '-' . (int) $stage->id;
        ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $first ? ' active' : ''; ?>"
                    id="<?php echo $tabSlug; ?>-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#<?php echo $tabSlug; ?>"
                    type="button"
                    role="tab"
                    aria-controls="<?php echo $tabSlug; ?>"
                    aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                <?php echo $stageTitle; ?>
                <span class="badge bg-secondary ms-1 wfb-count-badge"
                      data-stage-id="<?php echo (int) $stage->id; ?>"><?php echo $count; ?></span>
            </button>
        </li>
        <?php $first = false; endforeach; ?>
    </ul>

    <div class="tab-content wfb-tab-content mt-2">
        <?php $first = true; foreach ($stages as $stage) :
            $articles    = $stageData[$stage->id]['articles'] ?? [];
            $transitions = $helper->getTransitionsForStage((int) $stage->id, $workflowId, $user);
            $tabSlug     = 'wfb-tab-' . (int) $module->id . '-' . (int) $stage->id;
        ?>
        <div class="tab-pane fade<?php echo $first ? ' show active' : ''; ?>"
             id="<?php echo $tabSlug; ?>"
             role="tabpanel"
             aria-labelledby="<?php echo $tabSlug; ?>-btn"
             data-stage-id="<?php echo (int) $stage->id; ?>">

            <?php if (empty($articles)) : ?>
                <p class="text-muted text-center py-3">
                    <span class="icon-inbox me-1" aria-hidden="true"></span>
                    <?php echo Text::_('MOD_WORKFLOW_BOARD_NO_ARTICLES'); ?>
                </p>
            <?php else : ?>
            <div class="list-group list-group-flush wfb-tab-list"
                 data-stage-id="<?php echo (int) $stage->id; ?>">

                <?php foreach ($articles as $article) :
                    $canEdit      = $helper->canEditArticle($article, $user);
                    $isCheckedOut = !empty($article->checked_out) && (int) $article->checked_out !== (int) $user->id;
                    $articleTrans = $canEdit
                        ? array_values(array_filter($transitions, static fn($t) => $t->from_stage_id == -1 || (int) $t->from_stage_id === (int) $stage->id))
                        : [];
                    $stateInfo    = $stateMap[$article->state] ?? $stateMap[0];
                    $editUrl      = Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $article->id);
                    $relDate      = $helper->relativeDate($article->modified ?: $article->created);
                ?>
                <div class="list-group-item wfb-list-item d-flex align-items-start gap-2"
                     data-article-id="<?php echo (int) $article->id; ?>"
                     data-stage-id="<?php echo (int) $stage->id; ?>">

                    <!-- State icon -->
                    <span class="<?php echo $stateInfo['icon']; ?> mt-1 flex-shrink-0 wfb-tooltip"
                          title="<?php echo Text::_($stateInfo['key']); ?>"
                          aria-hidden="true"></span>

                    <!-- Main content -->
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex align-items-center gap-1">
                            <?php if ($isCheckedOut) : ?>
                                <span class="icon-lock text-warning wfb-tooltip"
                                      title="<?php echo Text::sprintf('MOD_WORKFLOW_BOARD_CHECKED_OUT_BY', htmlspecialchars($article->editor_name ?? '', ENT_QUOTES, 'UTF-8')); ?>"
                                      aria-hidden="true"></span>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>"
                               class="wfb-list-title text-truncate fw-semibold">
                                <?php echo htmlspecialchars($article->title, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                        <div class="wfb-list-meta small text-muted d-flex flex-wrap gap-2 mt-1">
                            <span><span class="icon-folder" aria-hidden="true"></span>
                                <?php echo htmlspecialchars($article->category_title ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><span class="icon-user" aria-hidden="true"></span>
                                <?php echo htmlspecialchars($article->author_name ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="wfb-tooltip"
                                  title="<?php echo htmlspecialchars($article->modified ?: $article->created, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="icon-clock" aria-hidden="true"></span>
                                <?php echo htmlspecialchars($relDate, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex-shrink-0 d-flex align-items-center gap-1">
                        <span class="wfb-state-badge" data-article-id="<?php echo (int) $article->id; ?>">
                            <?php echo $helper->renderStateBadge((int) $article->state); ?>
                        </span>

                        <?php if ($canEdit && !empty($articleTrans)) : ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    aria-label="<?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?>">
                                <span class="icon-exchange" aria-hidden="true"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end wfb-transition-list"
                                data-article-id="<?php echo (int) $article->id; ?>"
                                data-stage-id="<?php echo (int) $stage->id; ?>">
                                <?php foreach ($articleTrans as $trans) : ?>
                                <li>
                                    <button class="dropdown-item wfb-transition-btn"
                                            type="button"
                                            data-article-id="<?php echo (int) $article->id; ?>"
                                            data-transition-id="<?php echo (int) $trans->id; ?>"
                                            data-to-stage="<?php echo (int) $trans->to_stage_id; ?>">
                                        <span class="icon-arrow-right me-1" aria-hidden="true"></span>
                                        <?php echo htmlspecialchars(Text::_($trans->title), ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </div><!-- /.list-group -->
            <?php endif; ?>
        </div><!-- /.tab-pane -->
        <?php $first = false; endforeach; ?>
    </div><!-- /.tab-content -->

    <?php endif; // kanban / tabs ?>
<?php endif; // workflow enabled ?>

</div><!-- /.mod-workflow-board -->
