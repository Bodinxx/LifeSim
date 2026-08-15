<?php
/**
 * LifeSim — Application entry point
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LifeSim — a browser-based life simulation game.">
    <title>LifeSim</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-layout">
    <header class="app-header">
        <span class="app-header__title">LifeSim</span>
        <nav class="app-header__nav" aria-label="Main navigation">
            <span id="save-indicator" class="save-indicator" aria-live="polite"></span>
        </nav>
    </header>

    <main class="app-main" id="app-main">
        <section id="start-screen" class="start-screen" aria-label="Start screen">
            <h1 class="start-screen__title">LifeSim</h1>
            <p class="start-screen__tagline">
                Guide a character from birth to the end of a simulated lifetime.
                Every choice shapes the story.
            </p>
            <div class="start-screen__actions">
                <button id="btn-new-game" class="btn btn-primary">New Life</button>
                <button id="btn-continue" class="btn btn-secondary hidden">Continue</button>
            </div>
        </section>

        <section id="game-screen" class="hidden" aria-label="Game">
            <div class="character-panel" aria-label="Character status">
                <div class="character-panel__name" id="char-name">—</div>
                <div class="character-panel__age" id="char-age">Age 0</div>
                <div class="character-panel__origin" id="char-origin">—</div>
                <div class="attributes-grid">
                    <div class="attribute-item attribute-item--bar" data-attribute="health">
                        <div class="attribute-item__header">
                            <span class="attribute-item__label">Health</span>
                            <span class="attribute-item__value" id="attr-health">—</span>
                        </div>
                        <div class="attribute-bar"><span class="attribute-bar__fill" id="attr-health-bar"></span></div>
                    </div>
                    <div class="attribute-item attribute-item--bar" data-attribute="happiness">
                        <div class="attribute-item__header">
                            <span class="attribute-item__label">Happiness</span>
                            <span class="attribute-item__value" id="attr-happiness">—</span>
                        </div>
                        <div class="attribute-bar"><span class="attribute-bar__fill" id="attr-happiness-bar"></span></div>
                    </div>
                    <div class="attribute-item attribute-item--bar" data-attribute="intelligence">
                        <div class="attribute-item__header">
                            <span class="attribute-item__label">Intelligence</span>
                            <span class="attribute-item__value" id="attr-intel">—</span>
                        </div>
                        <div class="attribute-bar"><span class="attribute-bar__fill" id="attr-intel-bar"></span></div>
                    </div>
                    <div class="attribute-item attribute-item--bar" data-attribute="appearance">
                        <div class="attribute-item__header">
                            <span class="attribute-item__label">Appearance</span>
                            <span class="attribute-item__value" id="attr-appear">—</span>
                        </div>
                        <div class="attribute-bar"><span class="attribute-bar__fill" id="attr-appear-bar"></span></div>
                    </div>
                    <div class="attribute-item attribute-item--bar" data-attribute="stress">
                        <div class="attribute-item__header">
                            <span class="attribute-item__label">Stress</span>
                            <span class="attribute-item__value" id="attr-stress">—</span>
                        </div>
                        <div class="attribute-bar"><span class="attribute-bar__fill" id="attr-stress-bar"></span></div>
                    </div>
                    <div class="attribute-item">
                        <span class="attribute-item__label">Money</span>
                        <span class="attribute-item__value" id="attr-money">—</span>
                    </div>
                </div>
            </div>

            <div class="event-feed" aria-label="Life events">
                <p class="event-feed__title">Life Log</p>
                <div class="event-log" id="event-log" aria-live="polite"></div>
            </div>

            <div class="action-bar">
                <button id="btn-age-up" class="btn btn-primary">Age +1</button>
                <button id="btn-new-game-2" class="btn btn-ghost">New Life</button>
            </div>
        </section>
    </main>

    <footer class="app-footer">
        <small>LifeSim v<?= h(LIFESIM_VERSION) ?> — Licensed under AGPL-3.0</small>
    </footer>
</div>

<div class="modal-overlay hidden" id="character-creation-overlay" aria-modal="true" role="dialog" aria-labelledby="creation-title">
    <div class="modal modal--wide">
        <div class="modal__title-row">
            <h2 class="modal__title" id="creation-title">New Life</h2>
        </div>
        <div class="creation-summary" id="creation-summary"></div>
        <div class="modal__actions">
            <button id="btn-reroll-character" class="btn btn-secondary">Reroll</button>
            <button id="btn-start-life" class="btn btn-primary">Start Life</button>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="event-overlay" aria-modal="true" role="dialog" aria-labelledby="event-overlay-title">
    <div class="modal">
        <div class="modal__title-row">
            <h2 class="modal__title" id="event-overlay-title">Life Event</h2>
        </div>
        <div class="modal__body" id="event-overlay-body"></div>
        <div class="event-overlay__choices" id="event-overlay-choices"></div>
        <div class="modal__actions" id="event-overlay-actions">
            <button id="btn-event-overlay-continue" class="btn btn-primary">Continue</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>

<script src="js/character.js"></script>
<script src="js/save.js"></script>
<script src="js/app.js"></script>
</body>
</html>
