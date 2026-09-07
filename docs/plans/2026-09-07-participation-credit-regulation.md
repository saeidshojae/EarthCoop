# Participation Credit Regulation — Implementation Plan

## Goal
Create a user-facing «نظام‌نامه اعتبارات مشارکت» page that explains the live EarthCoop reputation/participation system while rendering every numeric rule from the same runtime sources used by the application.

## Source-of-truth rules
- `reputation_rules` is authoritative per action when a database rule exists.
- `config/reputation.php` remains the runtime fallback for rules not yet persisted, matching `ReputationService::applyAction()`.
- `config('reputation.tiers')` remains the source of truth for current reputation tiers because `ReputationService::determineLevel()` uses it.
- `MonetaryPolicyService::current()` is the source of truth for conversion enabled/disabled state, point-to-Gol ratio, and policy version/source.
- No numeric scoring/conversion values are duplicated in Blade copy.

## Tasks
1. Add a source-contract test for route, presenter, dynamic policy sources, required sections, and link from «امتیازات من».
2. Add `ParticipationCreditRegulationService` to normalize action labels/groups, dimensions, repeat policies, tiers, and monetary conversion state.
3. Add `ParticipationCreditRegulationController` and an authenticated named route.
4. Build a responsive unified-layout page modeled on the elections guideline, with live-policy badges and tables.
5. Add a prominent link/button in the reputation/points tab of «مشارکت‌های من».
6. Run focused reputation tests and relevant route/view/UI contract tests before completion.

## Required page sections
- What participation credits/reputation are
- Four reputation dimensions
- Reputation tiers (Bronze/Silver/Gold/Platinum) from live config
- Complete live scoring table: active state, weight/penalty, dimension, convertibility, rolling daily cap, repeat policy
- Point-to-Bahar conversion rules, including Gol granularity and current policy version/source
- Difference between total reputation, convertible awarded points, remaining conversion capacity, and consumed conversion capacity
- Anti-abuse/repetition/cap rules
- FAQ and link back to «مشارکت‌های من»

## Safety invariant
The regulation page is read-only. It must not seed, mutate, or rewrite reputation rules merely because a user reads it.
