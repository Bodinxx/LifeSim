/**
 * LifeSim — Main application controller
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

    const FIRST_NAMES = {
        male: ['Liam', 'Noah', 'Ethan', 'Lucas', 'Mason', 'Oliver', 'James', 'Henry', 'Leo', 'Owen'],
        female: ['Olivia', 'Emma', 'Ava', 'Sophia', 'Mia', 'Amelia', 'Ella', 'Grace', 'Chloe', 'Ruby'],
        'non-binary': ['Riley', 'Avery', 'Quinn', 'Taylor', 'Jordan', 'Casey', 'Alex', 'Morgan', 'Parker', 'Reese'],
    };
    const MAX_RELATION_ENTRIES_PER_YEAR = 4;
    const MAX_LIVING_FRIENDS = 12;
    const LAST_NAMES = ['Smith', 'Patel', 'Johnson', 'Garcia', 'Nguyen', 'Brown', 'Taylor', 'Lee', 'Wilson', 'Martin'];
    const PET_NAMES = ['Milo', 'Luna', 'Coco', 'Nala', 'Buddy', 'Pepper', 'Poppy', 'Rocky', 'Nova', 'Scout'];
    const PET_SPECIES = ['Dog', 'Cat', 'Rabbit', 'Parrot'];

    let _state = {
        screen: 'start',
        character: null,
        draftCharacter: null,
        modules: {},
        events: [],
        countries: [],
        cities: {},
        professions: [],
        worldEvents: [],
        overlayQueue: [],
        activeOverlay: null,
    };

    async function init() {
        await _loadContent();
        _bindUI();
        _restoreOrShowStart();
    }

    async function _loadContent() {
        const loadJson = async (path, fallback) => {
            try {
                const res = await fetch(path);
                if (!res.ok) throw new Error(String(res.status));
                return await res.json();
            } catch (err) {
                console.warn(`[App] Could not load ${path}:`, err);
                return fallback;
            }
        };

        const [events, countries, cities, professions, worldEvents] = await Promise.all([
            loadJson('data/events.json', []),
            loadJson('data/countries.json', []),
            loadJson('data/cities.json', {}),
            loadJson('data/professions.json', []),
            loadJson('data/world_events.json', []),
        ]);

        _state.events = Array.isArray(events) ? events.filter((event) => event.enabled !== false) : [];
        _state.countries = Array.isArray(countries) ? countries.filter((country) => country.enabled !== false) : [];
        _state.cities = cities && typeof cities === 'object' && !Array.isArray(cities) ? cities : {};
        _state.professions = Array.isArray(professions) ? professions.filter((profession) => profession.enabled !== false) : [];
        _state.worldEvents = Array.isArray(worldEvents) ? worldEvents.filter((event) => event.enabled !== false) : [];
    }

    function _bindUI() {
        _on('btn-new-game', 'click', _handleNewGame);
        _on('btn-continue', 'click', _handleContinue);
        _on('btn-age-up', 'click', _handleAgeUp);
        _on('btn-new-game-2', 'click', _handleNewGame);
        _on('btn-reroll-character', 'click', _showCharacterCreation);
        _on('btn-start-life', 'click', _startDraftLife);
        _on('btn-event-overlay-continue', 'click', _resolveActiveOverlay);
    }

    function _on(id, event, handler) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, handler);
    }

    function _restoreOrShowStart() {
        const save = SaveSystem.readSave();
        if (save && save.character) {
            _state.character = Character.normalize(save.character);
            _state.modules = save.modules || {};
            _showScreen('game');
            _renderGame();
        } else {
            _showScreen('start');
        }
    }

    function _showScreen(screen) {
        ['start-screen', 'game-screen'].forEach((id) => {
            document.getElementById(id)?.classList.add('hidden');
        });

        const targetMap = { start: 'start-screen', game: 'game-screen' };
        document.getElementById(targetMap[screen])?.classList.remove('hidden');
        _state.screen = screen;
        document.getElementById('btn-continue')?.classList.toggle('hidden', !SaveSystem.hasSave());
    }

    function _handleNewGame() {
        _showCharacterCreation();
    }

    function _showCharacterCreation() {
        _state.draftCharacter = _generateCharacter();
        _renderCharacterSummary(_state.draftCharacter);
        _toggleOverlay('character-creation-overlay', true);
    }

    function _startDraftLife() {
        if (!_state.draftCharacter) return;
        _state.character = Character.normalize(_state.draftCharacter);
        _state.modules = {};
        _toggleOverlay('character-creation-overlay', false);
        SaveSystem.saveNow(_state.character, {});
        _showScreen('game');
        _renderGame();
    }

    function _handleContinue() {
        const save = SaveSystem.readSave();
        if (!save || !save.character) return;
        _state.character = Character.normalize(save.character);
        _state.modules = save.modules || {};
        _showScreen('game');
        _renderGame();
    }

    function _handleAgeUp() {
        if (!_state.character || _state.activeOverlay) return;

        let character = Character.normalize(Object.assign({}, _state.character, {
            age: _state.character.age + 1,
            lifeStage: Character.lifeStageFromAge(_state.character.age + 1),
            updatedAt: new Date().toISOString(),
        }));

        character = Character.adjustAttribute(character, 'health', _randomRange(-3, 1));
        character = Character.adjustAttribute(character, 'stress', _randomRange(-5, 5));
        character = Character.recordEvent(character, `${character.name} turned ${character.age}.`, 'age');
        character = _simulateRelations(character);

        const queue = [];
        const worldEvent = _pickWorldEvent();
        if (worldEvent) {
            queue.push({
                kind: 'world',
                title: `World Event — ${worldEvent.name}`,
                description: worldEvent.description,
                entries: [{ age: character.age, text: worldEvent.description, category: 'world' }],
            });
        }

        const event = _pickRandomEvent();
        if (event) {
            queue.push({
                kind: event.choices && event.choices.length ? 'choice-event' : 'auto-event',
                title: event.name || 'Life Event',
                description: event.description,
                event,
            });
        }

        _state.character = character;
        _renderGame();

        if (queue.length) {
            _state.overlayQueue = queue;
            _showNextOverlay();
        } else {
            SaveSystem.scheduleAutoSave(_state.character, _state.modules);
        }
    }

    function _pickRandomEvent() {
        if (!_state.events.length || Math.random() > 0.5) return null;
        return _state.events[Math.floor(Math.random() * _state.events.length)];
    }

    function _pickWorldEvent() {
        if (!_state.worldEvents.length || Math.random() > 0.5) return null;
        return _state.worldEvents[Math.floor(Math.random() * _state.worldEvents.length)];
    }

    function _showNextOverlay() {
        if (!_state.overlayQueue.length) {
            _state.activeOverlay = null;
            _toggleOverlay('event-overlay', false);
            _renderGame();
            SaveSystem.scheduleAutoSave(_state.character, _state.modules);
            return;
        }

        _state.activeOverlay = _state.overlayQueue.shift();
        _renderActiveOverlay();
    }

    function _renderActiveOverlay() {
        const overlay = _state.activeOverlay;
        if (!overlay) {
            _toggleOverlay('event-overlay', false);
            return;
        }

        const body = document.getElementById('event-overlay-body');
        const choices = document.getElementById('event-overlay-choices');
        const actions = document.getElementById('event-overlay-actions');
        if (!body || !choices || !actions) {
            _state.activeOverlay = null;
            _state.overlayQueue = [];
            _toggleOverlay('event-overlay', false);
            return;
        }
        _setText('event-overlay-title', overlay.title || 'Life Event');
        body.textContent = overlay.description || '';
        choices.innerHTML = '';

        if (overlay.kind === 'choice-event' && overlay.event?.choices?.length) {
            actions.classList.add('hidden');
            overlay.event.choices.forEach((choice, index) => {
                const btn = document.createElement('button');
                btn.className = 'choice-btn';
                btn.textContent = choice.text;
                btn.addEventListener('click', () => _resolveChoiceOverlay(index));
                choices.appendChild(btn);
            });
        } else {
            actions.classList.remove('hidden');
        }

        _toggleOverlay('event-overlay', true);
    }

    function _resolveChoiceOverlay(index) {
        const overlay = _state.activeOverlay;
        if (!overlay || overlay.kind !== 'choice-event') return;
        const choice = overlay.event.choices[index];
        if (!choice) return;

        let character = Character.applyConsequence(_state.character, choice.consequence || {});
        character = Character.appendHistoryEntries(character, [
            { age: character.age, text: overlay.description, category: 'event' },
            { age: character.age, text: choice.outcome || choice.text, category: 'event' },
        ]);

        _state.character = character;
        _showNextOverlay();
    }

    function _resolveActiveOverlay() {
        const overlay = _state.activeOverlay;
        if (!overlay) return;

        let character = _state.character;
        if (overlay.kind === 'world') {
            character = Character.appendHistoryEntries(character, overlay.entries || []);
        } else if (overlay.kind === 'auto-event') {
            character = Character.applyConsequence(character, overlay.event?.consequence || {});
            character = Character.recordEvent(character, overlay.description, 'event');
        }

        _state.character = character;
        _showNextOverlay();
    }

    function _renderGame() {
        const character = _state.character;
        if (!character) return;

        _setText('char-name', character.name);
        _setText('char-age', `Age ${character.age} — ${character.lifeStage}`);
        const originParts = [character.cityOfOrigin, character.countryOfOrigin].filter(Boolean).join(', ');
        const originLine = [character.gender, originParts, `Birthday ${character.birthday.display}`].filter(Boolean).join(' • ');
        _setText('char-origin', originLine || '—');
        _setProgress('attr-health', 'attr-health-bar', character.health);
        _setProgress('attr-happiness', 'attr-happiness-bar', character.happiness);
        _setProgress('attr-intel', 'attr-intel-bar', character.intelligence);
        _setProgress('attr-appear', 'attr-appear-bar', character.appearance);
        _setProgress('attr-stress', 'attr-stress-bar', character.stress);
        _setText('attr-money', `$${Number(character.money || 0).toLocaleString()}`);
        _renderEventLog(character.eventHistory || []);
    }

    function _renderEventLog(history) {
        const container = document.getElementById('event-log');
        if (!container) return;
        container.innerHTML = '';

        const grouped = new Map();
        history.forEach((entry) => {
            if (!entry?.text) return;
            const age = Number(entry.age ?? 0);
            if (!grouped.has(age)) grouped.set(age, []);
            grouped.get(age).push(entry);
        });

        [...grouped.keys()].sort((a, b) => a - b).forEach((age) => {
            const group = document.createElement('section');
            group.className = 'event-log__age-group';

            const title = document.createElement('h3');
            title.className = 'event-log__age';
            title.textContent = `Age ${age}`;
            group.appendChild(title);

            const list = document.createElement('ul');
            list.className = 'event-log__list';
            grouped.get(age).forEach((entry) => {
                const item = document.createElement('li');
                item.textContent = entry.text;
                item.className = `event-log__item event-log__item--${entry.category || 'life'}`;
                list.appendChild(item);
            });
            group.appendChild(list);
            container.appendChild(group);
        });

        container.scrollTop = container.scrollHeight;
    }

    function _renderCharacterSummary(character) {
        const root = document.getElementById('creation-summary');
        if (!root) return;
        const parentRelations = character.relations.filter((relation) => relation.relationType === 'parent');
        const siblingRelations = character.relations.filter((relation) => relation.relationType === 'sibling');
        const petRelations = character.relations.filter((relation) => relation.relationType === 'pet');

        root.innerHTML = `
            <div class="creation-summary__grid">
                <section class="creation-card">
                    <h3>Identity</h3>
                    <ul>
                        <li><strong>Name:</strong> ${_escapeHtml(character.name)}</li>
                        <li><strong>Gender:</strong> ${_escapeHtml(character.gender)}</li>
                        <li><strong>Born in:</strong> ${_escapeHtml(character.cityOfOrigin)}, ${_escapeHtml(character.countryOfOrigin)}</li>
                        <li><strong>Birthday:</strong> ${_escapeHtml(character.birthday.display)}</li>
                    </ul>
                </section>
                <section class="creation-card">
                    <h3>Family</h3>
                    <ul>
                        <li><strong>Household:</strong> ${_escapeHtml(character.family.householdType)}</li>
                        <li><strong>Parents / guardians:</strong> ${parentRelations.length ? parentRelations.map((relation) => `${relation.name} (${relation.professionName || 'Unknown profession'})`).join(', ') : 'None listed'}</li>
                        <li><strong>Older siblings:</strong> ${siblingRelations.length ? siblingRelations.map((relation) => relation.name).join(', ') : 'None'}</li>
                        <li><strong>Pets:</strong> ${petRelations.length ? petRelations.map((relation) => `${relation.name} the ${relation.species}`).join(', ') : 'No household pet'}</li>
                    </ul>
                </section>
            </div>
        `;
    }

    function _generateCharacter() {
        const gender = _randomFrom(Character.GENDERS);
        const country = _randomFrom(_state.countries) || { code: 'CAN', name: 'Canada' };
        const cityList = _getCitiesForCountry(country.code);
        const city = _randomFrom(cityList) || 'Unknown City';
        const firstName = _randomFirstName(gender);
        const lastName = _randomFrom(LAST_NAMES);
        const birthdayMonth = _randomRange(1, 12);
        const birthdayDay = _randomRange(1, 28);
        const birthday = {
            month: birthdayMonth,
            day: birthdayDay,
            display: Character.formatBirthday(birthdayMonth, birthdayDay),
        };

        const familyScenario = _randomFrom([
            'both parents',
            'single mother',
            'single father',
            'guardian',
            'no active guardian',
        ]);

        const parents = _generateParents(familyScenario, lastName);
        const siblings = _generateSiblings(lastName);
        const pets = Math.random() < 0.4 ? [_generatePet()] : [];
        const relations = [...parents, ...siblings, ...pets];

        let character = Character.create({
            firstName,
            lastName,
            name: `${firstName} ${lastName}`,
            gender,
            pronouns: Character.pronounsForGender(gender),
            birthplace: `${city}, ${country.name}`,
            countryOfOrigin: country.name,
            cityOfOrigin: city,
            birthday,
            family: {
                householdType: familyScenario,
                parentIds: parents.map((relation) => relation.id),
                siblingIds: siblings.map((relation) => relation.id),
            },
            relations,
        });

        const birthEntries = [
            { age: 0, text: `${character.name} was born in ${character.cityOfOrigin}, ${character.countryOfOrigin}.`, category: 'life' },
            { age: 0, text: `${character.name}'s birthday is ${character.birthday.display}.`, category: 'life' },
        ];

        if (parents.length) {
            birthEntries.push({
                age: 0,
                text: `${character.name} begins life with ${parents.map((relation) => `${relation.name}, age ${relation.age}, ${relation.professionName || 'without a listed profession'}`).join(' and ')}.`,
                category: 'relation',
            });
        } else {
            birthEntries.push({ age: 0, text: `${character.name} begins life without active parental support.`, category: 'relation' });
        }

        if (siblings.length) {
            birthEntries.push({
                age: 0,
                text: `Older siblings: ${siblings.map((relation) => relation.name).join(', ')}.`,
                category: 'relation',
            });
        }

        if (pets.length) {
            birthEntries.push({ age: 0, text: `Household pet: ${pets[0].name} the ${pets[0].species}.`, category: 'relation' });
        }

        character = Character.appendHistoryEntries(character, birthEntries);
        return character;
    }

    function _generateParents(familyScenario, lastName) {
        const professions = _state.professions.length ? _state.professions : [];
        const makeParent = (label, gender, householdRole) => {
            const profession = _randomFrom(professions);
            const level = profession?.levels?.length ? profession.levels[Math.floor(Math.random() * profession.levels.length)] : null;
            const firstName = _randomFirstName(gender);
            return Character.createRelation({
                relationType: 'parent',
                label,
                firstName,
                lastName,
                name: `${firstName} ${lastName}`,
                gender,
                age: _randomRange(24, 46),
                householdRole,
                professionId: profession?.id || '',
                professionName: level ? `${level.title} (${profession.name})` : profession?.name || '',
                professionLevel: level ? profession.levels.indexOf(level) : 0,
                educationStage: level?.education || profession?.education || 'secondary',
                healthStatus: 'healthy',
                bond: _randomRange(55, 90),
            });
        };

        if (familyScenario === 'single mother') return [makeParent('mother', 'female', 'single mother')];
        if (familyScenario === 'single father') return [makeParent('father', 'male', 'single father')];
        if (familyScenario === 'guardian') return [makeParent('guardian', 'non-binary', 'guardian')];
        if (familyScenario === 'no active guardian') return [];
        return [makeParent('mother', 'female', 'mother'), makeParent('father', 'male', 'father')];
    }

    function _generateSiblings(lastName) {
        const count = _randomRange(0, 3);
        const siblings = [];
        for (let index = 0; index < count; index += 1) {
            const gender = _randomFrom(Character.GENDERS);
            const firstName = _randomFirstName(gender);
            siblings.push(Character.createRelation({
                relationType: 'sibling',
                label: 'sibling',
                firstName,
                lastName,
                name: `${firstName} ${lastName}`,
                gender,
                age: _randomRange(1, 12),
                educationStage: 'childhood',
                bond: _randomRange(40, 85),
            }));
        }
        return siblings;
    }

    function _generatePet() {
        return Character.createRelation({
            relationType: 'pet',
            label: 'pet',
            name: _randomFrom(PET_NAMES),
            species: _randomFrom(PET_SPECIES),
            age: _randomRange(1, 8),
            gender: _randomFrom(Character.GENDERS),
            bond: _randomRange(60, 95),
        });
    }

    function _simulateRelations(character) {
        const professions = _state.professions;
        const entries = [];
        const relations = (character.relations || []).map((relation) => {
            const next = Character.createRelation(Object.assign({}, relation));
            if (!next.alive) return next;
            next.age += 1;

            if (next.relationType === 'pet') {
                if (next.age > 12 && Math.random() < 0.12) {
                    next.alive = false;
                    next.healthStatus = 'deceased';
                    entries.push({ age: character.age, text: `${next.name} the ${next.species} passed away.`, category: 'relation' });
                }
                return next;
            }

            if (next.relationType === 'sibling' || next.relationType === 'friend') {
                if ([5, 13, 18, 22].includes(next.age)) {
                    next.educationStage = _educationStageForAge(next.age);
                    entries.push({ age: character.age, text: `${next.name} moved into ${next.educationStage}.`, category: 'relation' });
                }
            }

            if (professions.length && next.age >= 18 && Math.random() < 0.18) {
                const profession = professions.find((item) => item.id === next.professionId) || _randomFrom(professions);
                const nextLevel = Math.min(next.professionLevel + (Math.random() < 0.45 ? 1 : 0), Math.max((profession?.levels?.length || 1) - 1, 0));
                next.professionId = profession?.id || next.professionId;
                next.professionLevel = nextLevel;
                const level = profession?.levels?.[nextLevel];
                if (level) {
                    next.professionName = `${level.title} (${profession.name})`;
                    if (Math.random() < 0.35) {
                        entries.push({ age: character.age, text: `${next.name} is now working as ${level.title.toLowerCase()}.`, category: 'relation' });
                    }
                }
            }

            if (Math.random() < 0.05) {
                next.healthStatus = 'ill';
                entries.push({ age: character.age, text: `${next.name} is dealing with an illness this year.`, category: 'relation' });
            } else if (next.healthStatus === 'ill') {
                next.healthStatus = 'healthy';
            }

            const deathChance = next.age > 80 ? 0.12 : next.age > 65 ? 0.04 : 0.002;
            if (Math.random() < deathChance) {
                next.alive = false;
                next.healthStatus = 'deceased';
                entries.push({ age: character.age, text: `${next.name} passed away at age ${next.age}.`, category: 'relation' });
            }

            return next;
        });

        const livingFriends = relations.filter((relation) => relation.relationType === 'friend' && relation.alive);
        if (character.age >= 5 && livingFriends.length < MAX_LIVING_FRIENDS && Math.random() < 0.25) {
            const gender = _randomFrom(Character.GENDERS);
            const friendFirstName = _randomFirstName(gender);
            const friendLastName = _randomFrom(LAST_NAMES);
            const friend = Character.createRelation({
                relationType: 'friend',
                label: 'friend',
                firstName: friendFirstName,
                lastName: friendLastName,
                name: `${friendFirstName} ${friendLastName}`,
                gender,
                age: Math.max(0, character.age + _randomRange(-2, 2)),
                educationStage: _educationStageForAge(character.age),
                bond: _randomRange(45, 80),
            });
            relations.push(friend);
            entries.push({ age: character.age, text: `${character.name} became friends with ${friend.name}.`, category: 'relation' });
        }

        if (character.age >= 8 && !relations.some((relation) => relation.relationType === 'pet' && relation.alive) && Math.random() < 0.12) {
            const pet = _generatePet();
            relations.push(pet);
            entries.push({ age: character.age, text: `${character.name}'s household adopted ${pet.name} the ${pet.species}.`, category: 'relation' });
        }

        let nextCharacter = Character.normalize(Object.assign({}, character, { relations, relationships: relations }));
        if (entries.length) {
            nextCharacter = Character.appendHistoryEntries(nextCharacter, entries.slice(0, MAX_RELATION_ENTRIES_PER_YEAR));
        }
        return nextCharacter;
    }

    function _getCitiesForCountry(countryCode) {
        const cities = _state.cities[countryCode] || _state.cities[String(countryCode).toUpperCase()] || [];
        return Array.isArray(cities) ? cities : [];
    }

    function _educationStageForAge(age) {
        if (age < 5) return 'infancy';
        if (age < 13) return 'primary school';
        if (age < 18) return 'secondary school';
        if (age < 23) return 'tertiary education';
        return 'adult life';
    }

    function _setProgress(valueId, barId, value) {
        const numericValue = Math.max(0, Math.min(100, Number(value) || 0));
        _setText(valueId, numericValue);
        const bar = document.getElementById(barId);
        if (bar) {
            bar.style.width = `${numericValue}%`;
            bar.setAttribute('aria-valuenow', String(numericValue));
        }
    }

    function _setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function _toggleOverlay(id, visible) {
        const overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.classList.toggle('hidden', !visible);
        document.body.style.overflow = visible ? 'hidden' : '';
    }

    function _randomFirstName(gender) {
        const pool = FIRST_NAMES[gender] || FIRST_NAMES['non-binary'];
        return _randomFrom(pool);
    }

    function _randomRange(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function _randomFrom(items) {
        return Array.isArray(items) && items.length ? items[Math.floor(Math.random() * items.length)] : null;
    }

    function _escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => App.init());
