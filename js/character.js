/**
 * LifeSim — Character data model
 *
 * Defines the character schema and factory functions.
 * All character objects must conform to this structure.
 */

const Character = (() => {
    'use strict';

    /** Default attribute values for a newborn character. */
    const DEFAULTS = {
        health:      100,
        happiness:   70,
        intelligence: 50,
        appearance:  50,
        discipline:  30,
        stress:      0,
        reputation:  50,
        money:       0,
        annualIncome: 0,
    };

    /**
     * Create a new character object.
     * @param {object} overrides  Optional field overrides.
     * @returns {object}
     */
    function create(overrides = {}) {
        const now = new Date().toISOString();
        return Object.assign(
            {
                id:         generateUUID(),
                name:       '',
                pronouns:   'they/them',
                birthplace: '',
                age:        0,
                lifeStage:  'infancy',

                // Visible attributes
                health:       DEFAULTS.health,
                happiness:    DEFAULTS.happiness,
                intelligence: DEFAULTS.intelligence,
                appearance:   DEFAULTS.appearance,
                stress:       DEFAULTS.stress,
                reputation:   DEFAULTS.reputation,
                money:        DEFAULTS.money,
                annualIncome: DEFAULTS.annualIncome,

                // Semi-hidden modifiers
                discipline:   DEFAULTS.discipline,

                // Biographical
                education:    'none',          // none / primary / secondary / post-secondary
                employment:   'unemployed',    // unemployed / part-time / full-time / retired

                // Relationship records (populated in later phases)
                relationships: [],

                // Memories / consequence flags
                memories: [],

                // Event history (brief records)
                eventHistory: [],

                // Metadata
                createdAt:    now,
                updatedAt:    now,
            },
            overrides
        );
    }

    /**
     * Determine the life stage label from age.
     * @param {number} age
     * @returns {string}
     */
    function lifeStageFromAge(age) {
        if (age < 3)  return 'infancy';
        if (age < 13) return 'childhood';
        if (age < 18) return 'adolescence';
        if (age < 30) return 'young adulthood';
        if (age < 60) return 'adulthood';
        if (age < 75) return 'middle age';
        return 'senior years';
    }

    /**
     * Clamp a numeric attribute between 0 and 100.
     * @param {object} character
     * @param {string} attr
     * @param {number} delta
     * @returns {object}  New character object (non-mutating).
     */
    function adjustAttribute(character, attr, delta) {
        const NUMERIC_ATTRS = [
            'health', 'happiness', 'intelligence', 'appearance',
            'discipline', 'stress', 'reputation',
        ];
        if (!NUMERIC_ATTRS.includes(attr)) {
            return character;
        }
        const current = character[attr] ?? 0;
        const next    = Math.max(0, Math.min(100, current + delta));
        return Object.assign({}, character, { [attr]: next });
    }

    /**
     * Add a memory / flag to the character.
     * @param {object} character
     * @param {string} key   Short identifier for the memory.
     * @param {*}      value Associated value (default true).
     * @returns {object}  New character object.
     */
    function addMemory(character, key, value = true) {
        const memories = [...(character.memories || [])];
        const existing = memories.findIndex(m => m.key === key);
        if (existing >= 0) {
            memories[existing] = { key, value };
        } else {
            memories.push({ key, value });
        }
        return Object.assign({}, character, { memories });
    }

    /**
     * Check whether a character has a specific memory.
     * @param {object} character
     * @param {string} key
     * @returns {boolean}
     */
    function hasMemory(character, key) {
        return (character.memories || []).some(m => m.key === key);
    }

    /**
     * Append a brief event record to the character's history.
     * @param {object} character
     * @param {string} description
     * @returns {object}  New character object.
     */
    function recordEvent(character, description) {
        const entry = {
            age: character.age,
            description,
            timestamp: new Date().toISOString(),
        };
        const eventHistory = [...(character.eventHistory || []), entry];
        return Object.assign({}, character, { eventHistory, updatedAt: entry.timestamp });
    }

    /** Crypto-quality UUID v4. */
    function generateUUID() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        // Fallback for environments without crypto.randomUUID
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    return {
        create,
        lifeStageFromAge,
        adjustAttribute,
        addMemory,
        hasMemory,
        recordEvent,
    };
})();
