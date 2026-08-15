/**
 * LifeSim — Character data model
 */

const Character = (() => {
    'use strict';

    const DEFAULTS = {
        health: 100,
        happiness: 70,
        intelligence: 50,
        appearance: 50,
        discipline: 30,
        stress: 0,
        reputation: 50,
        money: 0,
        annualIncome: 0,
    };

    const GENDERS = ['male', 'female', 'non-binary'];

    function baseCharacter() {
        const now = new Date().toISOString();
        return {
            id: generateUUID(),
            firstName: '',
            lastName: '',
            name: '',
            gender: 'non-binary',
            pronouns: 'they/them',
            birthplace: '',
            countryOfOrigin: '',
            cityOfOrigin: '',
            birthday: {
                month: 1,
                day: 1,
                display: 'January 1',
            },
            age: 0,
            lifeStage: 'infancy',
            health: DEFAULTS.health,
            happiness: DEFAULTS.happiness,
            intelligence: DEFAULTS.intelligence,
            appearance: DEFAULTS.appearance,
            discipline: DEFAULTS.discipline,
            stress: DEFAULTS.stress,
            reputation: DEFAULTS.reputation,
            money: DEFAULTS.money,
            annualIncome: DEFAULTS.annualIncome,
            education: 'none',
            employment: 'unemployed',
            family: {
                householdType: 'unknown',
                parentIds: [],
                siblingIds: [],
            },
            relations: [],
            relationships: [],
            memories: [],
            eventHistory: [],
            createdAt: now,
            updatedAt: now,
        };
    }

    function create(overrides = {}) {
        return normalize(Object.assign(baseCharacter(), overrides));
    }

    function normalize(raw = {}) {
        const base = baseCharacter();
        const firstName = String(raw.firstName ?? '').trim();
        const lastName = String(raw.lastName ?? '').trim();
        const combinedName = [firstName, lastName].filter(Boolean).join(' ').trim();
        const fallbackName = String(raw.name ?? '').trim();
        const name = combinedName || fallbackName;
        const gender = GENDERS.includes(raw.gender) ? raw.gender : 'non-binary';
        const birthday = normalizeBirthday(raw.birthday);
        const country = String(raw.countryOfOrigin ?? '').trim();
        const city = String(raw.cityOfOrigin ?? '').trim();
        const birthplace = String(raw.birthplace ?? '').trim() || [city, country].filter(Boolean).join(', ');
        const relations = Array.isArray(raw.relations)
            ? raw.relations.map(normalizeRelation)
            : Array.isArray(raw.relationships)
                ? raw.relationships.map(normalizeRelation)
                : [];
        const family = normalizeFamily(raw.family, relations);
        const eventHistory = Array.isArray(raw.eventHistory)
            ? raw.eventHistory
                .filter((entry) => entry && typeof entry === 'object')
                .map(normalizeHistoryEntry)
            : [];

        return Object.assign({}, base, raw, {
            id: String(raw.id ?? base.id),
            firstName: firstName || extractFirstName(name),
            lastName: lastName || extractLastName(name),
            name: name || [extractFirstName(fallbackName), extractLastName(fallbackName)].filter(Boolean).join(' ').trim(),
            gender,
            pronouns: String(raw.pronouns ?? pronounsForGender(gender)),
            birthplace,
            countryOfOrigin: country,
            cityOfOrigin: city,
            birthday,
            age: toWholeNumber(raw.age, 0),
            lifeStage: String(raw.lifeStage ?? lifeStageFromAge(toWholeNumber(raw.age, 0))) || lifeStageFromAge(toWholeNumber(raw.age, 0)),
            health: clampScore(raw.health ?? base.health),
            happiness: clampScore(raw.happiness ?? base.happiness),
            intelligence: clampScore(raw.intelligence ?? base.intelligence),
            appearance: clampScore(raw.appearance ?? base.appearance),
            discipline: clampScore(raw.discipline ?? base.discipline),
            stress: clampScore(raw.stress ?? base.stress),
            reputation: clampScore(raw.reputation ?? base.reputation),
            money: toWholeNumber(raw.money, 0),
            annualIncome: toWholeNumber(raw.annualIncome, 0),
            family,
            relations,
            relationships: relations,
            memories: Array.isArray(raw.memories) ? raw.memories : [],
            eventHistory,
            createdAt: String(raw.createdAt ?? base.createdAt),
            updatedAt: String(raw.updatedAt ?? base.updatedAt),
        });
    }

    function normalizeBirthday(raw) {
        const month = clampValue(toWholeNumber(raw?.month, 1), 1, 12);
        const day = clampValue(toWholeNumber(raw?.day, 1), 1, 31);
        return {
            month,
            day,
            display: String(raw?.display ?? formatBirthday(month, day)),
        };
    }

    function normalizeFamily(raw, relations) {
        const family = raw && typeof raw === 'object' ? raw : {};
        const parentIds = Array.isArray(family.parentIds) ? family.parentIds.map(String) : relations.filter((relation) => relation.relationType === 'parent').map((relation) => relation.id);
        const siblingIds = Array.isArray(family.siblingIds) ? family.siblingIds.map(String) : relations.filter((relation) => relation.relationType === 'sibling').map((relation) => relation.id);
        return {
            householdType: String(family.householdType ?? 'unknown'),
            parentIds,
            siblingIds,
        };
    }

    function normalizeRelation(raw = {}) {
        const relationType = String(raw.relationType ?? raw.type ?? 'other');
        const firstName = String(raw.firstName ?? '').trim();
        const lastName = String(raw.lastName ?? '').trim();
        const name = String(raw.name ?? [firstName, lastName].filter(Boolean).join(' ')).trim();
        return {
            id: String(raw.id ?? generateUUID()),
            relationType,
            label: String(raw.label ?? relationType),
            firstName,
            lastName,
            name,
            gender: GENDERS.includes(raw.gender) ? raw.gender : 'non-binary',
            age: toWholeNumber(raw.age, 0),
            alive: raw.alive !== false,
            bond: clampScore(raw.bond ?? 50),
            householdRole: String(raw.householdRole ?? ''),
            professionId: String(raw.professionId ?? ''),
            professionName: String(raw.professionName ?? ''),
            professionLevel: toWholeNumber(raw.professionLevel, 0),
            educationStage: String(raw.educationStage ?? 'none'),
            healthStatus: String(raw.healthStatus ?? 'healthy'),
            statusText: String(raw.statusText ?? ''),
            species: String(raw.species ?? ''),
            origin: String(raw.origin ?? ''),
            notes: Array.isArray(raw.notes) ? raw.notes.map(String) : [],
            metadata: raw.metadata && typeof raw.metadata === 'object' && !Array.isArray(raw.metadata) ? raw.metadata : {},
        };
    }

    function createRelation(overrides = {}) {
        return normalizeRelation(overrides);
    }

    function createHistoryEntry(age, text, category = 'life', extra = {}) {
        return Object.assign({
            age: toWholeNumber(age, 0),
            text: String(text ?? '').trim(),
            category: String(category || 'life'),
            timestamp: new Date().toISOString(),
        }, extra);
    }

    function normalizeHistoryEntry(entry = {}) {
        return {
            age: toWholeNumber(entry.age, 0),
            text: String(entry.text ?? entry.description ?? '').trim(),
            category: String(entry.category ?? 'life'),
            timestamp: String(entry.timestamp ?? new Date().toISOString()),
        };
    }

    function lifeStageFromAge(age) {
        if (age < 3) return 'infancy';
        if (age < 13) return 'childhood';
        if (age < 18) return 'adolescence';
        if (age < 30) return 'young adulthood';
        if (age < 60) return 'adulthood';
        if (age < 75) return 'middle age';
        return 'senior years';
    }

    function adjustAttribute(character, attr, delta) {
        const numericAttrs = ['health', 'happiness', 'intelligence', 'appearance', 'discipline', 'stress', 'reputation'];
        if (!numericAttrs.includes(attr)) {
            return character;
        }
        const current = Number(character[attr] ?? 0);
        return normalize(Object.assign({}, character, { [attr]: clampScore(current + delta), updatedAt: new Date().toISOString() }));
    }

    function applyConsequence(character, consequence = {}) {
        if (!consequence || typeof consequence !== 'object' || Array.isArray(consequence)) {
            return character;
        }

        const directNumericAttrs = ['money', 'annualIncome'];
        const next = Object.assign({}, character);
        let changed = false;

        Object.entries(consequence).forEach(([attr, rawDelta]) => {
            const delta = Number(rawDelta);
            if (!Number.isFinite(delta) || delta === 0) {
                return;
            }

            if (directNumericAttrs.includes(attr)) {
                const current = Number(next[attr] ?? 0);
                next[attr] = current + delta;
                changed = true;
                return;
            }

            const numericAttrs = ['health', 'happiness', 'intelligence', 'appearance', 'discipline', 'stress', 'reputation'];
            if (numericAttrs.includes(attr)) {
                next[attr] = clampScore(Number(next[attr] ?? 0) + delta);
                changed = true;
            }
        });

        return changed ? normalize(Object.assign({}, next, { updatedAt: new Date().toISOString() })) : normalize(character);
    }

    function addMemory(character, key, value = true) {
        const memories = [...(character.memories || [])];
        const existing = memories.findIndex((memory) => memory.key === key);
        if (existing >= 0) {
            memories[existing] = { key, value };
        } else {
            memories.push({ key, value });
        }
        return normalize(Object.assign({}, character, { memories, updatedAt: new Date().toISOString() }));
    }

    function hasMemory(character, key) {
        return (character.memories || []).some((memory) => memory.key === key);
    }

    function recordEvent(character, description, category = 'life', extra = {}) {
        const entry = createHistoryEntry(character.age, description, category, extra);
        const eventHistory = [...(character.eventHistory || []), entry];
        return normalize(Object.assign({}, character, { eventHistory, updatedAt: entry.timestamp }));
    }

    function appendHistoryEntries(character, entries = []) {
        let next = normalize(character);
        entries.forEach((entry) => {
            if (!entry || typeof entry !== 'object') {
                return;
            }
            const text = String(entry.text ?? '').trim();
            if (!text) {
                return;
            }
            const historyEntry = createHistoryEntry(entry.age ?? next.age, text, entry.category ?? 'life', entry);
            next = normalize(Object.assign({}, next, {
                eventHistory: [...(next.eventHistory || []), historyEntry],
                updatedAt: historyEntry.timestamp,
            }));
        });
        return next;
    }

    function generateUUID() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
            const random = (Math.random() * 16) | 0;
            const value = char === 'x' ? random : (random & 0x3) | 0x8;
            return value.toString(16);
        });
    }

    function pronounsForGender(gender) {
        if (gender === 'male') return 'he/him';
        if (gender === 'female') return 'she/her';
        return 'they/them';
    }

    function extractFirstName(name) {
        return String(name ?? '').trim().split(/\s+/).filter(Boolean)[0] || '';
    }

    function extractLastName(name) {
        const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
        return parts.length > 1 ? parts.slice(1).join(' ') : '';
    }

    function clampScore(value) {
        return clampValue(toWholeNumber(value, 0), 0, 100);
    }

    function clampValue(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function toWholeNumber(value, fallback) {
        const number = Number(value);
        return Number.isFinite(number) ? Math.round(number) : fallback;
    }

    function formatBirthday(month, day) {
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];
        return `${monthNames[month - 1] || monthNames[0]} ${day}`;
    }

    return {
        DEFAULTS,
        GENDERS,
        create,
        normalize,
        createRelation,
        createHistoryEntry,
        appendHistoryEntries,
        lifeStageFromAge,
        adjustAttribute,
        applyConsequence,
        addMemory,
        hasMemory,
        recordEvent,
        pronounsForGender,
        formatBirthday,
    };
})();
