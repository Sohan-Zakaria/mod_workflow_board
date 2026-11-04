/**
 * @package     mod_workflow_board
 * @subpackage  Joomla.Module
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * ES2022 module – loaded via <script type="module"> by WebAssetManager.
 * Handles:
 *   1. One-click Workflow Transition buttons (all layouts).
 *   2. Drag-and-drop between Kanban columns (when enabled).
 *   3. DOM update after successful AJAX transition (card move, badge refresh).
 *   4. Joomla.renderMessages() feedback.
 */

'use strict';

// ── Joomla Script Options ────────────────────────────────────────────────────
const cfg = Joomla.getOptions('mod_workflow_board', {});
const {
  ajaxUrl      = 'index.php?option=com_ajax&module=workflow_board&method=transition&format=json',
  token        = '',
  tokenField   = '',
  layoutMode   = 'kanban',
  enableDragDrop = false,
  strings      = {},
} = cfg;

const i18n = {
  transitioning : strings.transitioning  ?? 'Processing…',
  success       : strings.success        ?? 'Transition executed successfully.',
  error         : strings.error          ?? 'An error occurred. Please try again.',
  checkedOut    : strings.checkedOut     ?? 'This article is checked out.',
};

// ─────────────────────────────────────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Find the root module element that contains a given child element.
 * @param {Element} el
 * @returns {Element|null}
 */
function getBoard(el) {
  return el.closest('.mod-workflow-board');
}

/**
 * Update all stage-count badges inside a board.
 * @param {Element} board
 * @param {number}  fromStageId
 * @param {number}  toStageId
 */
function updateCountBadges(board, fromStageId, toStageId) {
  const update = (stageId, delta) => {
    board.querySelectorAll(`.wfb-count-badge[data-stage-id="${stageId}"]`).forEach((badge) => {
      const current = parseInt(badge.textContent, 10) || 0;
      badge.textContent = Math.max(0, current + delta);
    });
  };
  if (fromStageId) update(fromStageId, -1);
  if (toStageId)   update(toStageId,  +1);
}

/**
 * Rebuild the transition dropdown buttons inside a card/list-item after
 * the server returns new valid transitions.
 * @param {Element}        cardEl
 * @param {number}         articleId
 * @param {number}         newStageId
 * @param {Array<object>}  transitions  [{id, title, to}, …]
 */
function rebuildTransitionMenu(cardEl, articleId, newStageId, transitions) {
  const menu = cardEl.querySelector('.wfb-transition-list');
  if (!menu) return;

  menu.dataset.stageId = newStageId;
  menu.innerHTML = '';

  transitions.forEach(({ id, title, to }) => {
    const li  = document.createElement('li');
    const btn = document.createElement('button');
    btn.className        = 'dropdown-item wfb-transition-btn';
    btn.type             = 'button';
    btn.dataset.articleId    = articleId;
    btn.dataset.transitionId = id;
    btn.dataset.toStage      = to;
    btn.innerHTML = `<span class="icon-arrow-right me-1" aria-hidden="true"></span>${title}`;
    li.appendChild(btn);
    menu.appendChild(li);
  });

  // Hide the whole dropdown group if no transitions remain
  const group = cardEl.querySelector('.wfb-transition-menu, .dropdown');
  if (group) {
    group.hidden = transitions.length === 0;
  }
}

/**
 * Execute a workflow transition via com_ajax.
 * @param {number}  articleId
 * @param {number}  transitionId
 * @param {Element} cardEl        The article card / list-item DOM element.
 * @returns {Promise<object>}     The parsed JSON data payload.
 */
async function executeTransition(articleId, transitionId, cardEl) {
  const body = new URLSearchParams();
  body.set(token, '1');          // CSRF token field
  body.set('item_id',       articleId);
  body.set('transition_id', transitionId);

  cardEl.classList.add('wfb-card--transitioning');

  // Add a lightweight spinner overlay to the card
  const spinner = document.createElement('div');
  spinner.className = 'wfb-spinner-overlay';
  spinner.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">' + i18n.transitioning + '</span></div>';
  cardEl.style.position = 'relative';
  cardEl.appendChild(spinner);

  try {
    const response = await fetch(ajaxUrl, {
      method : 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body   : body.toString(),
    });

    if (!response.ok) {
      const errData = await response.json().catch(() => ({}));
      throw new Error(errData?.message ?? `HTTP ${response.status}`);
    }

    const json = await response.json();

    // com_ajax wraps the response in a "data" property
    const data = json?.data ?? json;

    if (!data?.success) {
      throw new Error(data?.message ?? i18n.error);
    }

    return data;
  } finally {
    cardEl.classList.remove('wfb-card--transitioning');
    spinner.remove();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  DOM manipulation after a successful transition
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Move a card DOM element to the target stage column / tab panel and
 * update all related counters and menus.
 * @param {Element} board
 * @param {Element} cardEl
 * @param {object}  data   Response payload from server.
 */
function applyTransitionResult(board, cardEl, data) {
  const { from_stage_id: fromId, to_stage_id: toId, article_state, state_badge, transitions, item_id } = data;

  // Update state badge HTML
  const stateBadge = cardEl.querySelector(`.wfb-state-badge[data-article-id="${item_id}"]`);
  if (stateBadge && state_badge) {
    stateBadge.innerHTML = state_badge;
  }

  // Update card's own data-stage-id
  cardEl.dataset.stageId = toId;

  // Update transition menus
  rebuildTransitionMenu(cardEl, item_id, toId, transitions ?? []);

  // ── Kanban: move card to target column ────────────────────────────────
  if (layoutMode === 'kanban') {
    const targetList = board.querySelector(`.wfb-card-list[data-stage-id="${toId}"]`);
    if (targetList) {
      // Remove "empty" placeholder if present
      const empty = targetList.querySelector('.wfb-empty-col');
      if (empty) empty.remove();

      targetList.prepend(cardEl);

      // Trigger success flash
      cardEl.classList.add('wfb-card--success');
      cardEl.addEventListener('animationend', () => cardEl.classList.remove('wfb-card--success'), { once: true });
    }
  }

  // ── Tabs: move list-item to target tab panel list ─────────────────────
  if (layoutMode === 'tabs') {
    const targetList = board.querySelector(`.wfb-tab-list[data-stage-id="${toId}"]`);
    const targetPane = board.querySelector(`.tab-pane[data-stage-id="${toId}"]`);

    if (targetList) {
      cardEl.dataset.stageId = toId;
      targetList.prepend(cardEl);
    } else if (targetPane) {
      // List might not exist yet (was empty); create it
      const newList = document.createElement('div');
      newList.className = 'list-group list-group-flush wfb-tab-list';
      newList.dataset.stageId = toId;
      newList.appendChild(cardEl);

      // Remove "empty" paragraph if present
      const emptyMsg = targetPane.querySelector('p.text-muted');
      if (emptyMsg) emptyMsg.remove();

      targetPane.appendChild(newList);
    }

    cardEl.classList.add('wfb-card--success');
    cardEl.addEventListener('animationend', () => cardEl.classList.remove('wfb-card--success'), { once: true });
  }

  // Update count badges
  updateCountBadges(board, fromId, toId);

  // Joomla system message
  Joomla.renderMessages({ message: [i18n.success] });
}

// ─────────────────────────────────────────────────────────────────────────────
//  Transition button handler
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Handles clicks on any .wfb-transition-btn element.
 * @param {Event} event
 */
async function onTransitionClick(event) {
  const btn = event.target.closest('.wfb-transition-btn');
  if (!btn) return;

  event.preventDefault();
  event.stopPropagation();

  const articleId    = parseInt(btn.dataset.articleId, 10);
  const transitionId = parseInt(btn.dataset.transitionId, 10);

  if (!articleId || !transitionId) return;

  // Climb up to find the card / list-item
  const cardEl = btn.closest('.wfb-card, .wfb-list-item');
  const board  = getBoard(btn);
  if (!cardEl || !board) return;

  // Close the dropdown
  const dropdownToggle = cardEl.querySelector('[data-bs-toggle="dropdown"]');
  if (dropdownToggle) {
    const bsDropdown = bootstrap.Dropdown.getInstance(dropdownToggle);
    if (bsDropdown) bsDropdown.hide();
  }

  try {
    const data = await executeTransition(articleId, transitionId, cardEl);
    applyTransitionResult(board, cardEl, data);
  } catch (err) {
    cardEl.classList.add('wfb-card--error');
    cardEl.addEventListener('animationend', () => cardEl.classList.remove('wfb-card--error'), { once: true });
    Joomla.renderMessages({ error: [err.message ?? i18n.error] });
    console.error('[mod_workflow_board] Transition error:', err);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Drag-and-drop (Kanban only)
// ─────────────────────────────────────────────────────────────────────────────

/** @type {Element|null} Currently dragged card element. */
let dragCard = null;
/** @type {Element|null} Ghost placeholder shown in drop target. */
let dragGhost = null;

function onDragStart(event) {
  const card = event.target.closest('.wfb-card[draggable="true"]');
  if (!card) return;
  dragCard = card;
  card.classList.add('wfb-dragging');
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', card.dataset.articleId);
}

function onDragEnd() {
  if (dragCard) dragCard.classList.remove('wfb-dragging');
  if (dragGhost) dragGhost.remove();
  dragCard = null;
  dragGhost = null;

  // Clean up drag-over highlights
  document.querySelectorAll('.wfb-drag-over').forEach((el) => el.classList.remove('wfb-drag-over'));
}

function onDragOver(event) {
  const list = event.target.closest('.wfb-card-list[data-drag-target="true"]');
  if (!list || !dragCard) return;
  event.preventDefault();
  event.dataTransfer.dropEffect = 'move';

  if (!dragGhost) {
    dragGhost = document.createElement('div');
    dragGhost.className = 'wfb-drag-ghost';
  }

  const col = list.closest('.wfb-column');
  if (col) col.classList.add('wfb-drag-over');
  list.classList.add('wfb-drag-over');

  // Position ghost
  const afterEl = getDragAfterElement(list, event.clientY);
  if (afterEl == null) {
    list.appendChild(dragGhost);
  } else {
    list.insertBefore(dragGhost, afterEl);
  }
}

function onDragLeave(event) {
  const list = event.target.closest('.wfb-card-list');
  if (!list) return;
  if (!list.contains(event.relatedTarget)) {
    list.classList.remove('wfb-drag-over');
    const col = list.closest('.wfb-column');
    if (col) col.classList.remove('wfb-drag-over');
    if (dragGhost) dragGhost.remove();
  }
}

async function onDrop(event) {
  const list = event.target.closest('.wfb-card-list[data-drag-target="true"]');
  if (!list || !dragCard) return;
  event.preventDefault();

  const toStageId   = parseInt(list.dataset.stageId, 10);
  const fromStageId = parseInt(dragCard.dataset.stageId, 10);
  const articleId   = parseInt(dragCard.dataset.articleId, 10);

  if (!toStageId || !articleId || toStageId === fromStageId) {
    onDragEnd();
    return;
  }

  // Find valid transition from fromStage → toStage in the card's dropdown
  const transitionBtn = dragCard.querySelector(
    `.wfb-transition-btn[data-to-stage="${toStageId}"]`
  );

  if (!transitionBtn) {
    Joomla.renderMessages({ warning: [i18n.error] });
    onDragEnd();
    return;
  }

  const transitionId = parseInt(transitionBtn.dataset.transitionId, 10);
  const board        = getBoard(dragCard);

  // Place card in new list immediately (optimistic UI)
  if (dragGhost) dragGhost.remove();
  list.appendChild(dragCard);
  dragCard.classList.remove('wfb-dragging');

  try {
    const data = await executeTransition(articleId, transitionId, dragCard);
    applyTransitionResult(board, dragCard, data);
  } catch (err) {
    // Roll back: move card back to original column
    const originalList = board?.querySelector(`.wfb-card-list[data-stage-id="${fromStageId}"]`);
    if (originalList) originalList.prepend(dragCard);
    dragCard.classList.add('wfb-card--error');
    dragCard.addEventListener('animationend', () => dragCard?.classList.remove('wfb-card--error'), { once: true });
    Joomla.renderMessages({ error: [err.message ?? i18n.error] });
    console.error('[mod_workflow_board] Drag-drop transition error:', err);
  } finally {
    dragCard = null;
    dragGhost = null;
    document.querySelectorAll('.wfb-drag-over').forEach((el) => el.classList.remove('wfb-drag-over'));
  }
}

/**
 * Determine which card the dragged item should be inserted before,
 * based on the vertical cursor position.
 * @param {Element} container
 * @param {number}  y          event.clientY
 * @returns {Element|null}
 */
function getDragAfterElement(container, y) {
  const draggables = [
    ...container.querySelectorAll('.wfb-card[draggable="true"]:not(.wfb-dragging)'),
  ];

  return draggables.reduce(
    (closest, child) => {
      const box    = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset, element: child };
      }
      return closest;
    },
    { offset: Number.NEGATIVE_INFINITY }
  ).element ?? null;
}

// ─────────────────────────────────────────────────────────────────────────────
//  Initialisation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Boot the module for a given board element.
 * @param {Element} board
 */
function initBoard(board) {
  // Delegate transition clicks to the board root (works for dynamically rebuilt menus)
  board.addEventListener('click', onTransitionClick);

  // Drag-and-drop (Kanban only)
  if (enableDragDrop && layoutMode === 'kanban') {
    board.addEventListener('dragstart',  onDragStart);
    board.addEventListener('dragend',    onDragEnd);
    board.addEventListener('dragover',   onDragOver);
    board.addEventListener('dragleave',  onDragLeave);
    board.addEventListener('drop',       onDrop);
  }
}

// Wait for DOM ready
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.mod-workflow-board').forEach(initBoard);
});

// Graceful support for Joomla's dynamic content loading (e.g., postMessage re-inits)
document.addEventListener('joomla:updated', (event) => {
  const target = event.detail?.element ?? document;
  target.querySelectorAll('.mod-workflow-board:not([data-wfb-init])').forEach((board) => {
    board.setAttribute('data-wfb-init', '1');
    initBoard(board);
  });
});
