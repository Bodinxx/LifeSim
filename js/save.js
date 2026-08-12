/**
 * LifeSim — Versioned save-file system
 *
 * Handles save creation, auto-save, load, and the migration framework.
 * Persists to localStorage under the key 'lifesim_save'.
 */

const SaveSystem = (() => {
    'use strict';

    const STORAGE_KEY     = 'lifesim_save';
    const SAVE_FORMAT_VER = 1;
    const GAME_VERSION    = '0.1.0';

    // Auto-save debounce timer (ms)
    const AUTO_SAVE_DELAY = 800;
    let   _autoSaveTimer  = null;

    // ---------------------------------------------------------------------------
    // Save structure
    // ---------------------------------------------------------------------------

    /**
     * Wrap character + module data in a versioned save envelope.
     * @param {object} character
     * @param {object} modules    Optional module-specific data keyed by module id.
     * @returns {object}  Save envelope.
     */
    function createSaveEnvelope(character, modules = {}) {
        const now = new Date().toISOString();
        return {
            saveFormatVersion: SAVE_FORMAT_VER,
            gameVersion:       GAME_VERSION,
            characterId:       character.id,
            character:         character,
            modules:           modules,
            moduleList:        Object.keys(modules),
            createdAt:         character.createdAt || now,
            modifiedAt:        now,
        };
    }

    // ---------------------------------------------------------------------------
    // Persistence
    // ---------------------------------------------------------------------------

    /**
     * Write a save envelope to localStorage.
     * @param {object} envelope
     * @returns {boolean}  true on success.
     */
    function writeSave(envelope) {
        try {
            const serialised = JSON.stringify(envelope);
            localStorage.setItem(STORAGE_KEY, serialised);
            _notifySaved();
            return true;
        } catch (err) {
            console.error('[SaveSystem] Failed to write save:', err);
            return false;
        }
    }

    /**
     * Read and return the current save from localStorage.
     * Runs migrations before returning.
     * @returns {object|null}  Migrated save envelope, or null if none exists.
     */
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

    /**
     * Delete the current save from localStorage.
     */
    function deleteSave() {
        localStorage.removeItem(STORAGE_KEY);
    }

    /**
     * Return true if a save exists in localStorage.
     */
    function hasSave() {
        return localStorage.getItem(STORAGE_KEY) !== null;
    }

    // ---------------------------------------------------------------------------
    // Auto-save
    // ---------------------------------------------------------------------------

    /**
     * Schedule an auto-save after a short debounce delay.
     * Call this after any meaningful game state change.
     * @param {object} character
     * @param {object} modules
     */
    function scheduleAutoSave(character, modules = {}) {
        if (_autoSaveTimer) {
            clearTimeout(_autoSaveTimer);
        }
        _autoSaveTimer = setTimeout(() => {
            const envelope = createSaveEnvelope(character, modules);
            writeSave(envelope);
            _autoSaveTimer = null;
        }, AUTO_SAVE_DELAY);
    }

    /**
     * Immediately save without debounce.
     * @param {object} character
     * @param {object} modules
     * @returns {boolean}
     */
    function saveNow(character, modules = {}) {
        if (_autoSaveTimer) {
            clearTimeout(_autoSaveTimer);
            _autoSaveTimer = null;
        }
        const envelope = createSaveEnvelope(character, modules);
        return writeSave(envelope);
    }

    // ---------------------------------------------------------------------------
    // Migration framework
    // ---------------------------------------------------------------------------

    /**
     * Ordered list of migration functions.
     * Each entry: { fromVersion: N, migrate: (envelope) => envelope }
     *
     * When a new save format is introduced, push a migration here that
     * transforms fromVersion → fromVersion+1.
     */
    const MIGRATIONS = [
        // Example (not yet needed):
        // {
        //     fromVersion: 1,
        //     migrate(envelope) {
        //         // Add a new field with a safe default
        //         envelope.character.newField = envelope.character.newField ?? 'default';
        //         return envelope;
        //     }
        // },
    ];

    /**
     * Run all applicable migrations on a loaded save envelope.
     * Updates saveFormatVersion after each successful step.
     * @param {object} envelope
     * @returns {object}  Migrated envelope.
     */
    function migrate(envelope) {
        if (!envelope || typeof envelope !== 'object') return envelope;

        let version = envelope.saveFormatVersion ?? 0;

        MIGRATIONS.forEach(({ fromVersion, migrate: fn }) => {
            if (version === fromVersion) {
                try {
                    envelope = fn(envelope);
                    version += 1;
                    envelope.saveFormatVersion = version;
                } catch (err) {
                    console.error(`[SaveSystem] Migration from v${fromVersion} failed:`, err);
                }
            }
        });

        return envelope;
    }

    // ---------------------------------------------------------------------------
    // Save indicator UI integration
    // ---------------------------------------------------------------------------

    function _notifySaved() {
        const el = document.getElementById('save-indicator');
        if (!el) return;
        el.textContent  = '✓ Saved';
        el.classList.add('visible');
        clearTimeout(el._hideTimer);
        el._hideTimer = setTimeout(() => el.classList.remove('visible'), 2500);
    }

    // ---------------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------------

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
