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

    <!-- Header -->
    <header class="app-header">
        <span class="app-header__title">LifeSim</span>
        <nav class="app-header__nav" aria-label="Main navigation">
            <span id="save-indicator" class="save-indicator" aria-live="polite"></span>
        </nav>
    </header>

    <!-- Main content -->
    <main class="app-main" id="app-main">

        <!-- Start screen -->
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

        <!-- Game screen -->
        <section id="game-screen" class="hidden" aria-label="Game">

            <!-- Character panel -->
            <div class="character-panel" aria-label="Character status">
                <div class="character-panel__name" id="char-name">—</div>
                <div class="character-panel__age"  id="char-age">Age 0</div>
                <div class="attributes-grid">
                    <div class="attribute-item">
                        <span class="attribute-item__label">Health</span>
                        <span class="attribute-item__value" id="attr-health">—</span>
                    </div>
                    <div class="attribute-item">
                        <span class="attribute-item__label">Happiness</span>
                        <span class="attribute-item__value" id="attr-happiness">—</span>
                    </div>
                    <div class="attribute-item">
                        <span class="attribute-item__label">Intelligence</span>
                        <span class="attribute-item__value" id="attr-intel">—</span>
                    </div>
                    <div class="attribute-item">
                        <span class="attribute-item__label">Appearance</span>
                        <span class="attribute-item__value" id="attr-appear">—</span>
                    </div>
                    <div class="attribute-item">
                        <span class="attribute-item__label">Stress</span>
                        <span class="attribute-item__value" id="attr-stress">—</span>
                    </div>
                    <div class="attribute-item">
                        <span class="attribute-item__label">Money</span>
                        <span class="attribute-item__value" id="attr-money">—</span>
                    </div>
                </div>
            </div>

            <!-- Decision panel (shown when an event needs a choice) -->
            <div id="decision-panel" class="event-decision hidden" aria-live="polite" aria-label="Event decision">
                <p class="event-decision__description" id="decision-description"></p>
                <div class="event-decision__choices" id="decision-choices"></div>
            </div>

            <!-- Event feed -->
            <div class="event-feed" aria-label="Life events">
                <p class="event-feed__title">Life Events</p>
                <ul class="event-list" id="event-list" aria-live="polite"></ul>
            </div>

            <!-- Action bar -->
            <div class="action-bar">
                <button id="btn-age-up"    class="btn btn-primary">Age +1</button>
                <button id="btn-new-game-2" class="btn btn-ghost">New Life</button>
            </div>

        </section>

    </main>

    <!-- Footer -->
    <footer class="app-footer">
        <small>LifeSim v<?= h(LIFESIM_VERSION) ?> — Licensed under AGPL-3.0</small>
    </footer>

</div>

<!-- Toast container (for future notifications) -->
<div class="toast-container" id="toast-container" aria-live="polite"></div>

<script src="js/character.js"></script>
<script src="js/save.js"></script>
<script src="js/app.js"></script>
</body>
</html>
