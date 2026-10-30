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

$moduleId = 'mod-workflow-board-' . $module->id;
$sfx      = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
?>
<div id="<?php echo $moduleId; ?>" class="mod-workflow-board<?php echo $sfx ? ' ' . $sfx : ''; ?>"
     data-module-id="<?php echo (int) $module->id; ?>"
     data-workflow-id="<?php echo $workflowId; ?>"
     data-layout="<?php echo htmlspecialchars($layoutMode, ENT_QUOTES, 'UTF-8'); ?>"
     data-drag-drop="<?php echo $enableDragDrop ? '1' : '0'; ?>">

<?php if (!$workflowEnabled) : ?>
    <!-- ── Workflow disabled notice ─────────────────────────────────── -->
    <div class="alert alert-warning d-flex align-items-center gap-2 m-3 shadow-sm rounded-3" role="alert">
        <span class="icon-exclamation-triangle fs-4 text-warning" aria-hidden="true"></span>
        <div class="flex-grow-1">
            <strong><?php echo Text::_('MOD_WORKFLOW_BOARD_DISABLED_TITLE'); ?></strong>
            <p class="mb-1 text-muted small"><?php echo Text::_('MOD_WORKFLOW_BOARD_DISABLED_DESC'); ?></p>
            <a href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_content'); ?>"
               class="btn btn-sm btn-warning mt-1">
                <span class="icon-cog me-1" aria-hidden="true"></span>
                <?php echo Text::_('MOD_WORKFLOW_BOARD_ENABLE_LINK'); ?>
            </a>
        </div>
    </div>

<?php elseif (empty($stages)) : ?>
    <!-- ── No stages found ────────────────────────────────────────────── -->
    <div class="alert alert-info d-flex align-items-center gap-2 m-3 rounded-3" role="alert">
        <span class="icon-info-circle fs-4 text-info" aria-hidden="true"></span>
        <div><?php echo Text::_('MOD_WORKFLOW_BOARD_NO_STAGES'); ?></div>
    </div>

<?php else : ?>

    <?php if ($layoutMode === 'kanban') : ?>
    <!-- ══════════════════════════════════════════════════════════════════
         KANBAN BOARD LAYOUT
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
                        <div class="wfb-col-header-main">
                            <div class="wfb-col-title-group">
                                <?php if ($stage->default) : ?>
                                    <span class="wfb-stage-icon wfb-default-star wfb-tooltip"
                                          title="<?php echo Text::_('MOD_WORKFLOW_BOARD_STAGE_DEFAULT_TIP'); ?>"
                                          aria-hidden="true">
                                        <span class="icon-star"></span>
                                    </span>
                                <?php else : ?>
                                    <span class="wfb-stage-dot" aria-hidden="true"></span>
                                <?php endif; ?>
                                <h6 class="wfb-col-title mb-0" title="<?php echo htmlspecialchars($stageTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($stageTitle, ENT_QUOTES, 'UTF-8'); ?>
                                </h6>
                                <?php if ($stageDesc) : ?>
                                    <span class="icon-info-circle wfb-tooltip wfb-desc-icon"
                                          title="<?php echo htmlspecialchars($stageDesc, ENT_QUOTES, 'UTF-8'); ?>"
                                          aria-hidden="true"></span>
                                <?php endif; ?>
                            </div>
                            <span class="wfb-count-badge" data-stage-id="<?php echo (int) $stage->id; ?>">
                                <?php echo $count; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Article cards list -->
                    <div class="wfb-card-list"
                         data-stage-id="<?php echo (int) $stage->id; ?>"
                         <?php if ($enableDragDrop) : ?>data-drag-target="true"<?php endif; ?>>

                    <?php if (empty($articles)) : ?>
                        <div class="wfb-empty-col">
                            <span class="icon-inbox wfb-empty-icon" aria-hidden="true"></span>
                            <span class="wfb-empty-text"><?php echo Text::_('MOD_WORKFLOW_BOARD_NO_ARTICLES'); ?></span>
                        </div>
                    <?php else : ?>

                        <?php foreach ($articles as $article) :
                            $canEdit      = $helper->canEditArticle($article, $user);
                            $isCheckedOut = !empty($article->checked_out) && (int) $article->checked_out !== (int) $user->id;
                            $articleTrans = $canEdit
                                ? array_values(array_filter($transitions, static fn($t) => $t->from_stage_id == -1 || (int) $t->from_stage_id === (int) $stage->id))
                                : [];
                            $editUrl      = Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $article->id);
                            $relDate      = $helper->relativeDate($article->modified ?: $article->created);
                            $author       = !empty($article->author_name) ? $article->author_name : Text::_('JNONE');
                        ?>
                        <div class="wfb-card<?php echo $isCheckedOut ? ' wfb-card--locked' : ''; ?><?php echo !$canEdit ? ' wfb-card--readonly' : ''; ?>"
                             data-article-id="<?php echo (int) $article->id; ?>"
                             data-stage-id="<?php echo (int) $stage->id; ?>"
                             <?php if ($enableDragDrop && $canEdit && !$isCheckedOut) : ?>draggable="true"<?php endif; ?>>

                            <!-- Card header: title + lock status -->
                            <div class="wfb-card-header">
                                <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="wfb-card-title"
                                   title="<?php echo htmlspecialchars($article->title, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($article->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <?php if ($isCheckedOut) : ?>
                                    <span class="wfb-lock-pill wfb-tooltip"
                                          title="<?php echo Text::sprintf('MOD_WORKFLOW_BOARD_CHECKED_OUT_BY', htmlspecialchars($article->editor_name ?? '', ENT_QUOTES, 'UTF-8')); ?>"
                                          aria-hidden="true">
                                        <span class="icon-lock"></span>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Card metadata chips -->
                            <div class="wfb-card-meta">
                                <?php if (!empty($article->category_title)) : ?>
                                <span class="wfb-meta-item wfb-category" title="<?php echo htmlspecialchars($article->category_title, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="icon-folder" aria-hidden="true"></span>
                                    <span><?php echo htmlspecialchars($article->category_title, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                                <?php endif; ?>

                                <span class="wfb-meta-item wfb-author" title="<?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="icon-user" aria-hidden="true"></span>
                                    <span><?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>

                                <span class="wfb-meta-item wfb-date wfb-tooltip"
                                      title="<?php echo htmlspecialchars($article->modified ?: $article->created, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="icon-clock" aria-hidden="true"></span>
                                    <span><?php echo htmlspecialchars($relDate, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </div>

                            <!-- Card footer: State badge + Transitions -->
                            <div class="wfb-card-footer">
                                <span class="wfb-state-badge" data-article-id="<?php echo (int) $article->id; ?>">
                                    <?php echo $helper->renderStateBadge((int) $article->state); ?>
                                </span>

                                <?php if ($canEdit && !empty($articleTrans)) : ?>
                                <div class="dropdown wfb-transition-menu">
                                    <button class="wfb-btn-transition dropdown-toggle"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            data-bs-display="static"
                                            aria-expanded="false"
                                            title="<?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?>"
                                            aria-label="<?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?>">
                                        <span class="icon-arrow-right me-1" aria-hidden="true"></span>
                                        <span><?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITION_BTN'); ?></span>
                                        <span class="icon-chevron-down ms-1 wfb-caret" aria-hidden="true"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end wfb-dropdown wfb-transition-list shadow-sm"
                                        data-article-id="<?php echo (int) $article->id; ?>"
                                        data-stage-id="<?php echo (int) $stage->id; ?>">
                                        <li class="dropdown-header small text-uppercase fw-bold text-muted py-1">
                                            <?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?>
                                        </li>
                                        <?php foreach ($articleTrans as $trans) : ?>
                                        <li>
                                            <button class="dropdown-item wfb-transition-btn d-flex align-items-center gap-2"
                                                    type="button"
                                                    data-article-id="<?php echo (int) $article->id; ?>"
                                                    data-transition-id="<?php echo (int) $trans->id; ?>"
                                                    data-to-stage="<?php echo (int) $trans->to_stage_id; ?>">
                                                <span class="icon-arrow-right text-primary" aria-hidden="true"></span>
                                                <span><?php echo htmlspecialchars(Text::_($trans->title), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </button>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php elseif ($isCheckedOut) : ?>
                                <span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1">
                                    <span class="icon-lock" aria-hidden="true"></span>
                                    <span><?php echo Text::_('JLIB_HTML_CHECKED_OUT'); ?></span>
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
        $tabId = 'wfb-tabs-' . (int) $module->id;
        $first = true;
    ?>
    <div class="wfb-tabs-wrapper">
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
                    <?php if ($stage->default) : ?>
                        <span class="icon-star text-warning me-1" aria-hidden="true"></span>
                    <?php endif; ?>
                    <?php echo $stageTitle; ?>
                    <span class="badge rounded-pill bg-secondary ms-1.5 wfb-count-badge"
                          data-stage-id="<?php echo (int) $stage->id; ?>"><?php echo $count; ?></span>
                </button>
            </li>
            <?php $first = false; endforeach; ?>
        </ul>

        <div class="tab-content wfb-tab-content" id="<?php echo $tabId; ?>-content">
            <?php $first = true; foreach ($stages as $stage) :
                $articles    = $stageData[$stage->id]['articles'] ?? [];
                $transitions = $helper->getTransitionsForStage((int) $stage->id, $workflowId, $user);
                $tabSlug     = 'wfb-tab-' . (int) $module->id . '-' . (int) $stage->id;
            ?>
            <div class="tab-pane fade<?php echo $first ? ' show active' : ''; ?>"
                 id="<?php echo $tabSlug; ?>"
                 role="tabpanel"
                 aria-labelledby="<?php echo $tabSlug; ?>-btn">

                <?php if (empty($articles)) : ?>
                    <div class="p-4 text-center text-muted">
                        <span class="icon-inbox fs-2 mb-2 d-block"></span>
                        <p class="mb-0"><?php echo Text::_('MOD_WORKFLOW_BOARD_NO_ARTICLES'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="list-group list-group-flush wfb-list-group">
                        <?php foreach ($articles as $article) :
                            $canEdit      = $helper->canEditArticle($article, $user);
                            $isCheckedOut = !empty($article->checked_out) && (int) $article->checked_out !== (int) $user->id;
                            $articleTrans = $canEdit
                                ? array_values(array_filter($transitions, static fn($t) => $t->from_stage_id == -1 || (int) $t->from_stage_id === (int) $stage->id))
                                : [];
                            $editUrl      = Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $article->id);
                            $relDate      = $helper->relativeDate($article->modified ?: $article->created);
                            $author       = !empty($article->author_name) ? $article->author_name : Text::_('JNONE');
                        ?>
                        <div class="list-group-item wfb-list-item d-flex align-items-center justify-content-between gap-3<?php echo $isCheckedOut ? ' wfb-card--locked' : ''; ?>">
                            <div class="d-flex align-items-center gap-2.5 flex-grow-1 min-w-0">
                                <span class="wfb-state-badge" data-article-id="<?php echo (int) $article->id; ?>">
                                    <?php echo $helper->renderStateBadge((int) $article->state); ?>
                                </span>
                                <div class="min-w-0">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <?php if ($isCheckedOut) : ?>
                                            <span class="icon-lock text-warning wfb-tooltip"
                                                  title="<?php echo Text::sprintf('MOD_WORKFLOW_BOARD_CHECKED_OUT_BY', htmlspecialchars($article->editor_name ?? '', ENT_QUOTES, 'UTF-8')); ?>"
                                                  aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                           class="wfb-list-title text-truncate fw-semibold"
                                           title="<?php echo htmlspecialchars($article->title, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($article->title, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </div>
                                    <div class="wfb-list-meta small text-muted d-flex align-items-center gap-2 mt-0.5">
                                        <?php if (!empty($article->category_title)) : ?>
                                        <span><span class="icon-folder me-1" aria-hidden="true"></span><?php echo htmlspecialchars($article->category_title, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <span><span class="icon-user me-1" aria-hidden="true"></span><?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><span class="icon-clock me-1" aria-hidden="true"></span><?php echo htmlspecialchars($relDate, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                <?php if ($canEdit && !empty($articleTrans)) : ?>
                                <div class="dropdown wfb-transition-menu">
                                    <button class="wfb-btn-transition dropdown-toggle"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            data-bs-display="static"
                                            aria-expanded="false"
                                            title="<?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?>">
                                        <span class="icon-arrow-right me-1" aria-hidden="true"></span>
                                        <span><?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITION_BTN'); ?></span>
                                        <span class="icon-chevron-down ms-1 wfb-caret" aria-hidden="true"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end wfb-dropdown wfb-transition-list shadow-sm"
                                        data-article-id="<?php echo (int) $article->id; ?>"
                                        data-stage-id="<?php echo (int) $stage->id; ?>">
                                        <li class="dropdown-header small text-uppercase fw-bold text-muted py-1">
                                            <?php echo Text::_('MOD_WORKFLOW_BOARD_TRANSITIONS_LABEL'); ?>
                                        </li>
                                        <?php foreach ($articleTrans as $trans) : ?>
                                        <li>
                                            <button class="dropdown-item wfb-transition-btn d-flex align-items-center gap-2"
                                                    type="button"
                                                    data-article-id="<?php echo (int) $article->id; ?>"
                                                    data-transition-id="<?php echo (int) $trans->id; ?>"
                                                    data-to-stage="<?php echo (int) $trans->to_stage_id; ?>">
                                                <span class="icon-arrow-right text-primary" aria-hidden="true"></span>
                                                <span><?php echo htmlspecialchars(Text::_($trans->title), ENT_QUOTES, 'UTF-8'); ?></span>
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
                    </div>
                <?php endif; ?>

            </div>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
