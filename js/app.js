/**
 * LifeSim — Main application controller
 *
 * Wires together the UI, character model, save system, and event library.
 * Depends on: character.js, save.js  (loaded before this file)
 */

const App = (() => {
    'use strict';

    const CONSEQUENCE_LABELS = {
        health: 'Health',
        happiness: 'Happiness',
        intelligence: 'Intelligence',
        appearance: 'Appearance',
        discipline: 'Discipline',
        stress: 'Stress',
        reputation: 'Reputation',
        money: 'Money',
        annualIncome: 'Annual income',
    };

    // -------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------

    let _state = {
        screen:    'start',   // 'start' | 'game' | 'decision'
        character: null,
        modules:   {},
        events:    [],        // loaded from data/events.json
        pendingEvent: null,   // current decision event awaiting response
    };

    // -------------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------------

    async function init() {
        await _loadEvents();
        _bindUI();
        _restoreOrShowStart();
    }

    async function _loadEvents() {
        try {
            const res  = await fetch('data/events.json');
            const data = await res.json();
            _state.events = Array.isArray(data) ? data.filter(e => e.enabled !== false) : [];
        } catch (err) {
            console.warn('[App] Could not load events:', err);
            _state.events = [];
        }
    }

    function _restoreOrShowStart() {
        const save = SaveSystem.readSave();
        if (save && save.character) {
            _state.character = save.character;
            _state.modules   = save.modules || {};
            _showScreen('game');
            _renderGame();
        } else {
            _showScreen('start');
        }
    }

    // -------------------------------------------------------------------
    // UI binding
    // -------------------------------------------------------------------

    function _bindUI() {
        _on('btn-new-game',   'click', _handleNewGame);
        _on('btn-continue',   'click', _handleContinue);
        _on('btn-age-up',     'click', _handleAgeUp);
        _on('btn-new-game-2', 'click', _handleNewGame);
    }

    function _on(id, event, handler) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, handler);
    }

    // -------------------------------------------------------------------
    // Screen management
    // -------------------------------------------------------------------

    function _showScreen(screen) {
        ['start-screen', 'game-screen'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        });
        const targets = {
            start: 'start-screen',
            game:  'game-screen',
        };
        const target = document.getElementById(targets[screen]);
        if (target) target.classList.remove('hidden');

        _state.screen = screen;

        // Show/hide Continue button based on save
        const btnContinue = document.getElementById('btn-continue');
        if (btnContinue) {
            btnContinue.classList.toggle('hidden', !SaveSystem.hasSave());
        }
    }

    // -------------------------------------------------------------------
    // New game
    // -------------------------------------------------------------------

    function _handleNewGame() {
        const name = _prompt('Enter your character\'s name (or leave blank for random):');
        const character = Character.create({
            name:      (name && name.trim()) || _randomName(),
            birthplace: 'Unknown',
        });
        _state.character = character;
        _state.modules   = {};
        SaveSystem.saveNow(character, {});
        _showScreen('game');
        _renderGame();
        _appendEvent(`${character.name} was born.`);
    }

    function _handleContinue() {
        const save = SaveSystem.readSave();
        if (save && save.character) {
            _state.character = save.character;
            _state.modules   = save.modules || {};
            _showScreen('game');
            _renderGame();
        }
    }

    // -------------------------------------------------------------------
    // Age up
    // -------------------------------------------------------------------

    function _handleAgeUp() {
        if (!_state.character) return;

        let c = _state.character;
        c = Object.assign({}, c, {
            age:       c.age + 1,
            lifeStage: Character.lifeStageFromAge(c.age + 1),
            updatedAt: new Date().toISOString(),
        });

        // Natural stat changes with age
        c = Character.adjustAttribute(c, 'health', _randomRange(-3, 1));
        c = Character.adjustAttribute(c, 'stress',  _randomRange(-5, 5));

        _state.character = c;
        _renderGame();
        _appendEvent(`${c.name} turned ${c.age}.`);

        // Possibly trigger a random event
        const event = _pickRandomEvent();
        if (event) {
            _triggerEvent(event);
        }

        SaveSystem.scheduleAutoSave(_state.character, _state.modules);
    }

    // -------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------

    function _pickRandomEvent() {
        if (!_state.events.length) return null;
        if (Math.random() > 0.5) return null; // ~50% chance per year
        const idx = Math.floor(Math.random() * _state.events.length);
        return _state.events[idx];
    }

    function _triggerEvent(event) {
        if (!event.choices || !event.choices.length) {
            // Automatic event — no player choice needed
            _state.character = _applyEventConsequence(
                _state.character,
                event.consequence,
                [event.description, _formatConsequence(event.consequence)],
                event.name || event.description
            );
            _renderGame();
            SaveSystem.scheduleAutoSave(_state.character, _state.modules);
            return;
        }
        // Decision event
        _state.pendingEvent = event;
        _renderDecision(event);
    }

    function _handleChoice(choice) {
        _clearDecision();
        _state.character = _applyEventConsequence(
            _state.character,
            choice.consequence,
            [choice.outcome, _formatConsequence(choice.consequence)],
            choice.text
        );
        _renderGame();
        SaveSystem.scheduleAutoSave(_state.character, _state.modules);
        _state.pendingEvent = null;
    }

    // -------------------------------------------------------------------
    // Render helpers
    // -------------------------------------------------------------------

    function _renderGame() {
        const c = _state.character;
        if (!c) return;

        _setText('char-name',      c.name);
        _setText('char-age',       `Age ${c.age} — ${c.lifeStage}`);
        _setText('attr-health',    c.health);
        _setText('attr-happiness', c.happiness);
        _setText('attr-intel',     c.intelligence);
        _setText('attr-appear',    c.appearance);
        _setText('attr-stress',    c.stress);
        _setText('attr-money',     `$${c.money.toLocaleString()}`);
    }

    function _renderDecision(event) {
        const panel = document.getElementById('decision-panel');
        if (!panel) return;

        _setText('decision-description', event.description);

        const container = document.getElementById('decision-choices');
        if (!container) return;
        container.innerHTML = '';

        event.choices.forEach((choice) => {
            const btn = document.createElement('button');
            btn.className = 'choice-btn';
            btn.textContent = choice.text;
            btn.addEventListener('click', () => _handleChoice(choice));
            container.appendChild(btn);
        });

        panel.classList.remove('hidden');
    }

    function _clearDecision() {
        const panel = document.getElementById('decision-panel');
        if (panel) panel.classList.add('hidden');
    }

    function _appendEvent(text) {
        const list = document.getElementById('event-list');
        if (!list) return;
        const li = document.createElement('li');
        li.className = 'event-list__item';
        li.textContent = text;
        list.prepend(li);
        // Keep list at a reasonable length
        while (list.children.length > 20) {
            list.removeChild(list.lastChild);
        }
    }

    function _appendEventEntries(entries) {
        entries
            .filter((entry) => typeof entry === 'string' && entry.trim() !== '')
            .reverse()
            .forEach((entry) => _appendEvent(entry));
    }

    function _applyEventConsequence(character, consequence, entries, historyText) {
        _appendEventEntries(entries);
        let next = Character.applyConsequence(character, consequence);
        if (historyText) {
            next = Character.recordEvent(next, historyText);
        }
        return next;
    }

    function _formatConsequence(consequence) {
        if (!consequence || typeof consequence !== 'object' || Array.isArray(consequence)) {
            return '';
        }

        const parts = Object.entries(consequence)
            .filter(([key, delta]) => CONSEQUENCE_LABELS[key] && Number.isFinite(Number(delta)) && Number(delta) !== 0)
            .map(([key, delta]) => {
                const amount = Number(delta);
                const sign = amount > 0 ? '+' : '';
                return `${CONSEQUENCE_LABELS[key]} ${sign}${amount}`;
            });

        return parts.length ? parts.join(', ') + '.' : '';
    }

    function _setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    // -------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------

    function _randomName() {
        const names = [
            'Alex', 'Jordan', 'Morgan', 'Taylor', 'Casey',
            'Riley', 'Avery', 'Quinn', 'Reese', 'Blake',
        ];
        return names[Math.floor(Math.random() * names.length)];
    }

    function _randomRange(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function _prompt(message) {
        // Use the native prompt; replace with custom modal in a later phase.
        return window.prompt(message) || '';
    }

    // -------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => App.init());
