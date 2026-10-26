# Toinky Module Ownership

Who is accountable for each of the 8 systems. Where no other owner is named
in the repo, the accountable role is **Randi (owner)** — the Super Admin.
Do not invent other people's names here; if a system gains a named owner,
record it with the date and the source (chat, doc, or commit).

## Systems

| # | System | Service files (app/) | Entry points | Accountable |
|---|--------|----------------------|--------------|-------------|
| 1 | Identity & Access | `AuthService.php`, `UserProfileService.php` | `public/profile.php`, `public/admin.php` (Users, User Accounts) | Randi (owner) / Super Admin |
| 2 | Story Authoring (Studio) | `StoryAuthoringService.php`, `StoryMediaService.php`, `MediaLibraryService.php`, `StoryCorrectionService.php`, `StoryCorrectionV2Service.php` | `public/writer.php`, `public/media.php` | Randi (owner); Writers author, Super Admin publishes |
| 3 | Story Engine (Simulation Runtime) | `SimulationService.php`, `BehaviorService.php`, `CausalService.php`, `ResolutionService.php`, `TriggeredTransitionService.php`, `DynamicOpportunityService.php`, `GenericStateService.php`, `StateSyncService.php`, `ConsequencePropagationService.php`, `FocusedOpportunitySimulationService.php`, `StorySimulationBatchService.php` | `public/lab.php`, `tools/simulate.php`, `tools/SimulationHarness.php` | Randi (owner) |
| 4 | Play Experience (Games & Lobbies) | `LobbyService.php`, `CharacterAutomationService.php` | `public/play.php`, `public/lobby.php`, `public/lobby-state.php` | Randi (owner) |
| 5 | Commerce (Occasions & Payments) | `CommerceService.php`, `StripeService.php` | `public/stripe-webhook.php` | Randi (owner); Stripe account is KickOff Creative |
| 6 | Public Website & Page Builder | `PageContentService.php`, `PageBuilderService.php`, `ParallaxService.php` | `public/index.php`, `public/page-builder.php` | Randi (owner) |
| 7 | AI Services | `AiReasoningContextService.php`, `AiInteractionLogger.php`, `AiSettingsService.php`, `AiConnectionService.php`, `OllamaStoryReasoner.php`, `DeterministicStoryReasoner.php`, `StoryReasoner.php`, `StoryReasonerFactory.php`, `InkyCowriterService.php`, `InkyGuideService.php`, `NarratorService.php` | `public/ai-settings.php`, `public/inky-*.php` | Randi (owner) |
| 8 | Platform & Infrastructure | `bootstrap.php`, `Logger.php`, `MigrationService.php`, `runtime_check.php` | `.cpanel.yml`, `tools/migrate.php`, `tools/deploy_htaccess.php`, `config/` | Randi (owner) |

## Rules

- **One accountable role per system.** If a change touches two systems, the
  owner of the system whose tables/behaviour change most is the decider.
- **Randi's verdict gates every creative call** (story content, images,
  prose). System owners do not approve creative changes on his behalf.
- **Super Admin rules** (migration 128): the Super Admin never needs occasion
  credits to host, can never be deactivated or demoted, and gets a two-factor
  email code after 2 weeks without a sign-in.
- **User types to plan around:** Super Admin, Admin, Writer, Player. Every
  menu/permission plan must account for all four.
- **No framework rewrite, no microservice migration.** The PHP/MySQL monolith
  and the deterministic story engine stay as they are; fixes go to the
  framework level, never workarounds.
