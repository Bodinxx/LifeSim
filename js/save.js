/**
 * LifeSim — Versioned save-file system
 */

const SaveSystem = (() => {
    'use strict';

    const STORAGE_KEY = 'lifesim_save';
    const SAVE_FORMAT_VER = 2;
    const GAME_VERSION = '0.1.0';
    const AUTO_SAVE_DELAY = 800;
    let _autoSaveTimer = null;

    function createSaveEnvelope(character, modules = {}) {
        const normalisedCharacter = Character.normalize(character);
        const now = new Date().toISOString();
        return {
            saveFormatVersion: SAVE_FORMAT_VER,
            gameVersion: GAME_VERSION,
            characterId: normalisedCharacter.id,
            character: normalisedCharacter,
            modules,
            moduleList: Object.keys(modules),
            createdAt: normalisedCharacter.createdAt || now,
            modifiedAt: now,
        };
    }

    function writeSave(envelope) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(envelope));
            _notifySaved();
            return true;
        } catch (err) {
            console.error('[SaveSystem] Failed to write save:', err);
            return false;
        }
    }

    function readSave() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            const envelope = JSON.parse(raw);
            return migrate(envelope);
        } catch (err) {
            console.error('[SaveSystem] Failed to read save:', err);
            return null;
        }
    }

    function deleteSave() {
        localStorage.removeItem(STORAGE_KEY);
    }

    function hasSave() {
        return localStorage.getItem(STORAGE_KEY) !== null;
    }

    function scheduleAutoSave(character, modules = {}) {
        if (_autoSaveTimer) {
            clearTimeout(_autoSaveTimer);
        }
        _autoSaveTimer = setTimeout(() => {
            writeSave(createSaveEnvelope(character, modules));
            _autoSaveTimer = null;
        }, AUTO_SAVE_DELAY);
    }

    function saveNow(character, modules = {}) {
        if (_autoSaveTimer) {
            clearTimeout(_autoSaveTimer);
            _autoSaveTimer = null;
        }
        return writeSave(createSaveEnvelope(character, modules));
    }

    const MIGRATIONS = [
        {
            fromVersion: 1,
            migrate(envelope) {
                const character = Character.normalize(envelope.character || {});
                return Object.assign({}, envelope, {
                    characterId: character.id,
                    character,
                    modules: envelope.modules || {},
                    moduleList: Object.keys(envelope.modules || {}),
                });
            },
        },
    ];

    function migrate(envelope) {
        if (!envelope || typeof envelope !== 'object') return envelope;

        let next = Object.assign({}, envelope);
        let version = next.saveFormatVersion ?? 1;

        MIGRATIONS.forEach(({ fromVersion, migrate: runMigration }) => {
            if (version === fromVersion) {
                try {
                    next = runMigration(next);
                    version += 1;
                    next.saveFormatVersion = version;
                } catch (err) {
                    console.error(`[SaveSystem] Migration from v${fromVersion} failed:`, err);
                }
            }
        });

        if (next.character) {
            next.character = Character.normalize(next.character);
            next.characterId = next.character.id;
        }

        next.modules = next.modules || {};
        next.moduleList = Object.keys(next.modules);
        next.saveFormatVersion = version;

        return next;
    }

    function _notifySaved() {
        const el = document.getElementById('save-indicator');
        if (!el) return;
        el.textContent = '✓ Saved';
        el.classList.add('visible');
        clearTimeout(el._hideTimer);
        el._hideTimer = setTimeout(() => el.classList.remove('visible'), 2500);
    }

    return {
        SAVE_FORMAT_VER,
        GAME_VERSION,
        createSaveEnvelope,
        writeSave,
        readSave,
        deleteSave,
        hasSave,
        scheduleAutoSave,
        saveNow,
        migrate,
    };
})();
