# LifeSim

LifeSim is a life-simulation game inspired by games such as *BitLife*, played entirely through a web browser. Players guide a character from birth through childhood, education, relationships, employment, adulthood, and eventually the end of their life.

Choices, personal attributes, random events, and previous decisions shape the character's evolving story.

## Project Status

LifeSim is currently in the planning and early-development stage.

The initial goal is to create a functional single-player prototype containing the complete basic life cycle. Additional systems, content, and presentation improvements will be introduced through later development phases.

## Project Goals

- Create an accessible browser-based life simulator.
- Support desktop computers, tablets, and mobile devices.
- Allow players to experience a complete simulated lifetime.
- Make player decisions meaningfully affect later opportunities and events.
- Support a large and expandable library of life events.
- Keep the underlying systems modular so new content can be added easily.
- Avoid requiring players to install an application or create an account.
- Make the game deployable on ordinary PHP-compatible web hosting.

## Technology

LifeSim will use PHP for server-side logic, HTML for structure, CSS for responsive presentation, JavaScript for the interactive interface, and JSON or PHP data files for configurable content during early development.

The project will not require a JavaScript framework, package manager, compilation process, or specialized application server. A database may be introduced later for accounts, cloud saves, shared statistics, or advanced administrative tools.

## Core Gameplay Loop

Each game follows the life of one character. During a typical turn, the player will:

1. Review the character's current age, attributes, relationships, and circumstances.
2. Choose activities or make decisions.
3. Advance the character's age.
4. Experience life events and the consequences of previous decisions.
5. Update relationships, finances, health, education, employment, and other statistics.
6. Continue until the character's life ends.
7. Review the completed life summary and optionally begin a new life.

## Character Attributes

The initial character system may include age, health, happiness, intelligence, appearance, discipline, stress, reputation, money, annual income, education, and employment status. Some attributes will be visible, while others may operate as hidden modifiers.

## Major Game Systems

### Character Creation

- Random or customized character generation
- Name and pronouns
- Birthplace and family circumstances
- Starting attributes
- Optional difficulty or life settings

### Age and Life Stages

Characters will progress through infancy, childhood, adolescence, young adulthood, adulthood, middle age, senior years, and the end of life. Available activities, decisions, and events will change according to age and circumstances.

### Family and Relationships

- Parents, guardians, siblings, and extended family
- Friends and relationship strength
- Romantic partners, marriage, and separation
- Children
- Relationship-specific activities and events

### Education

- Elementary and secondary school
- Academic performance, clubs, and extracurricular activities
- Discipline and behavioural events
- Post-secondary applications
- College, university, and vocational programs
- Tuition, scholarships, and student debt

### Employment and Careers

- Part-time employment
- Career qualifications and job applications
- Salaries and benefits
- Promotions and termination
- Workplace events
- Retirement
- Career-specific choices and opportunities

### Finances

- Income and expenses
- Bank balance
- Debt and loans
- Property and vehicles
- Taxes and inheritance
- Major purchases

Investments are reserved for a future optional module and are not part of the initial financial system.

### Health

- General health, illnesses, and injuries
- Medical treatment
- Exercise, diet, and lifestyle
- Mental wellness
- Aging and long-term conditions
- Causes of death

### Activities

Players will be able to perform age-appropriate activities between yearly age advances. Activities may affect attributes, relationships, finances, education, health, or future opportunities.

### Events and Decisions

Life events will be drawn from a configurable event library. Events may consider age, life stage, attributes, education, employment, relationships, finances, health, previous choices, random chance, and active storylines. Some events will happen automatically, while others will ask the player to choose between multiple responses.

### Consequences and Memories

Important decisions and events may create lasting character memories or flags. These records can influence future events, relationships, opportunities, and the final life summary.

## Saving and Compatibility

### Automatic Saving

Automatic saving is a core feature beginning in Phase 1 and will remain enabled regardless of the storage system. The game may eventually support local browser saves, exported save files, server-based saves, account-based cloud saves, and multiple save slots.

The game should save automatically after important actions such as creating a character, advancing age, resolving an event, making a major decision, completing an activity, or changing an important setting. The interface should clearly indicate when a game is saving and when the most recent save completed.

### Save-File Compatibility

Save files must remain portable between game versions. Updating LifeSim or installing a new module must not require players to recreate their characters.

Every save must include:

- A save-format version and game-version identifier
- A unique character identifier
- Core character data
- Module-specific data
- A list of modules represented in the save
- Creation and modification metadata

When an older save is opened, LifeSim will run the required migrations before gameplay continues. Save migrations must:

- Preserve existing character history and progress.
- Add reasonable defaults for newly introduced fields.
- Leave unrelated data unchanged.
- Run in the correct sequence when several migrations are required.
- Avoid silently discarding unknown module data.
- Create a backup before a major migration.
- Record the resulting save-format version.
- Report failures without overwriting the last valid save.

Modules must treat missing data as normal and initialize their portion of an older save using safe defaults. Removing or temporarily disabling a module must not automatically erase that module's stored character data.

## Administration Panel

The Administration Panel will exist from Phase 1 and expand alongside the game. Its first function will be managing random events. New administration features will be added as corresponding game systems are introduced.

### Initial Access

During initial development, the default administrator credentials will be:

- Username: `admin`
- Initial password: `admin`

The password must be stored as a secure hash, never as readable text. The administrator must replace the initial password during first-time setup. Until it is changed, the panel must display a prominent security warning and must not be exposed on a public installation. Production deployments must never retain `admin` as the active password.

### Phase 1 Administration Features

- Administrator login and logout
- Forced replacement of the initial password
- View, add, and edit random events
- Enable or disable events
- Define event names, descriptions, choices, and basic outcomes
- Validate required event fields
- Preview events
- Record administrative changes

### Progressive Administration Features

| Phase | Planned administration capabilities |
| --- | --- |
| Phase 1 | Random-event management, event choices, basic outcomes, and system settings |
| Phase 2 | Age ranges, life-stage requirements, attribute effects, and event frequency |
| Phase 3 | Activities, activity costs, limits, risks, and outcomes |
| Phase 4 | Relationships, education, employment, health, finance, and other life-system content |
| Phase 5 | Multi-part storylines, prerequisites, weighted outcomes, memories, and event testing |
| Phase 6 | Presentation settings, portraits, icons, themes, accessibility options, and onboarding content |
| Phase 7 | Achievements, challenges, unlocks, legacies, and progression settings |
| Phase 8 | Advanced content management, importing, exporting, validation, balance reports, permissions, and database-backed administration |
| Phase 9 | Deployment checks, maintenance tools, backups, migration reports, and release diagnostics |

## Modular Expansion

New systems should be introduced as modules whenever practical. A module may add data fields, activities, events, interface components, administration options, save migrations, character-history entries, and end-of-life statistics.

Potential modules include investments, expanded careers, business ownership, professional sports, politics, fame, and military careers. Installing a module must not invalidate an existing character.

## Development Phases

### Phase 1 — Project Foundation

Establish the technical structure, basic interface, saving framework, and initial Administration Panel.

- Create the project folder structure and modular architecture.
- Create the responsive application layout and basic navigation.
- Define the character data model and versioned save-file structure.
- Add automatic local saving and loading.
- Establish the save-migration framework.
- Create the Administration Panel and secure first-time setup.
- Add basic random-event management.
- Create development and debugging tools.

**Milestone:** The application can create, display, automatically save, load, and migrate a basic character. An administrator can securely access the Administration Panel and manage basic random events.

### Phase 2 — Core Life-Cycle Prototype

- Generate a new character and family.
- Advance the character by age and introduce life stages.
- Modify attributes over time.
- Present random events and decisions.
- Add basic education, employment, and relationships.
- Add aging, death, and an end-of-life summary.
- Expand administration options for age, life-stage, attribute, and frequency rules.

**Milestone:** A player can complete an entire simulated life from birth to death.

### Phase 3 — Activities and Player Choice

- Add age-appropriate activities.
- Add relationship interactions and health activities.
- Add school, career, and financial actions.
- Add activity limits, costs, risks, and outcomes.
- Improve short-term and long-term consequences.
- Add activity management to the Administration Panel.

**Milestone:** Player choices have clear short-term and long-term effects on the character's life.

### Phase 4 — Expanded Life Systems

- Expand family and relationship mechanics.
- Add dating, marriage, separation, and children.
- Expand education, qualifications, and career paths.
- Add promotions and workplace events.
- Add detailed finances, assets, and debt, excluding investments.
- Expand physical and mental health systems.
- Add crime, legal consequences, and reputation if appropriate.
- Add retirement, inheritance, and estate handling.
- Expand the Administration Panel for new life systems.

**Milestone:** Characters can follow substantially different life paths.

### Phase 5 — Event and Storyline Expansion

- Create a larger event library.
- Add multi-part storylines, rare events, and unusual opportunities.
- Add event prerequisites and weighted outcomes.
- Add character memories and long-term consequences.
- Add regional and cultural variations where practical.
- Add event-frequency and balance testing tools.

**Milestone:** Multiple playthroughs produce noticeably different stories.

### Phase 6 — User Experience and Presentation

- Refine the mobile and tablet interface.
- Improve navigation and information hierarchy.
- Add animations, visual feedback, portraits, and icons.
- Add themes, display options, and accessibility settings.
- Improve keyboard and touch controls.
- Add optional sound and music controls if audio is introduced.
- Add guided onboarding.

**Milestone:** The game feels polished and works comfortably across supported devices.

### Phase 7 — Progression and Replayability

Possible features include achievements, challenges, unlockable content, career and lifestyle collections, family legacies, generational play, life statistics, historical summaries, difficulty modes, and custom settings.

**Milestone:** Completing one life naturally encourages the player to begin another.

### Phase 8 — Advanced Administration and Content Tools

Mature the Administration Panel established during Phase 1 into a comprehensive content-management system.

- Advanced event editor
- Career, education, activity, health, and relationship editors
- Module configuration
- Content validation, import, and export
- Event simulation, frequency testing, and balance reports
- Save-migration reports
- Administrative change history
- Additional administrator accounts and permissions
- Database-backed content management

**Milestone:** Most game content and configuration can be created, tested, and maintained without editing source code.

### Phase 9 — Release Preparation

- Browser and device testing
- Performance optimization
- Save-file migration testing
- Security and accessibility reviews
- Error handling and recovery
- Privacy documentation and player instructions
- Deployment documentation
- Backup and update procedures

**Milestone:** LifeSim is stable enough for its first public release.

## Initial Release Scope

The first playable release will focus on:

- Single-player gameplay with one active character
- Random character generation and birth-to-death progression
- Core attributes and basic family relationships
- Basic education and employment
- Simple financial tracking without investments
- Activities, yearly decisions, and random life events
- Automatic local saving and loading
- Forward-compatible, versioned save files
- Basic random-event administration
- End-of-life summary
- Responsive desktop, tablet, and mobile layout

Advanced systems will remain outside the initial release until the complete life cycle is stable.

## Design Principles

### Decisions Should Matter

Important decisions should create consequences that may appear years later.

### Random but Understandable

Randomness will create variety, but outcomes should still reflect the character's attributes, circumstances, and previous choices.

### Simple Interface, Deep Systems

The game should be easy to understand while allowing complex interactions beneath the surface.

### Modular Content

Events, careers, activities, illnesses, education options, and other content should be separated from the core game engine whenever practical.

### Durable Characters

Characters and their histories must survive game upgrades and the addition, removal, or temporary disabling of optional modules.

### Mobile-First Design

All major features must work on a phone or tablet without requiring precise mouse controls or a desktop-sized display.

### Respectful Simulation

Sensitive topics should be handled thoughtfully and should contribute meaningfully to gameplay rather than exist solely for shock value.

## Potential Future Features

These ideas are not committed to the initial roadmap:

- User accounts and cloud saves
- Generational family play
- Custom scenarios and community-created event packs
- Multiple countries, regions, or historical settings
- Pets
- Fame, politics, business ownership, professional sports, and military careers
- Investment module
- Multiplayer comparisons or challenges
- Global player statistics
- Progressive Web App installation and offline play
- Localization and multiple languages

## Current Priorities

1. Finalize the project requirements.
2. Define the character and versioned save-data structures.
3. Establish the application architecture and migration framework.
4. Build the responsive interface shell.
5. Create the initial Administration Panel and event editor.
6. Develop the complete core life-cycle prototype.
7. Expand content only after the core systems are working reliably.

## License

LifeSim is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

This licence permits others to use, study, modify, and redistribute the project. Modified versions made available to users—including versions hosted as web services—must also make their corresponding source code available under the AGPL v3.

The complete licence terms will be included in the project's `LICENSE` file before the first release.

## Contributions

LifeSim is currently in early development. Contribution guidelines will be added once the project structure and development workflow have been established.
