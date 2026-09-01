# Membership Participation Gate Design

## Goal
EarthCoop participation that creates economic/reputation consequences must begin only after the member has entered Najm Bahar and paid the current membership fee. This change applies narrowly to member invitations and group-chat message composition/sending.

## Canonical eligibility states
A shared service returns one of three states for a user:

- `no_najm_bahar_account`: no canonical Najm Bahar main account exists.
- `membership_fee_due`: a main account exists, but the membership fee for the current membership period has not been paid.
- `eligible`: a main account exists and the current membership-period fee is paid.

The current membership period follows the existing membership-fee controller rule based on the user's membership anniversary. A paid period is proven by the canonical Najm Bahar membership-fee transactions and accepts both current split names (`operations_salary`, `central_insurance`, `money_destruction`) and legacy split names (`membership`, `insurance`, `burn`).

## Invitation gate
Existing profile completeness and invitation quota rules remain unchanged. In addition, a member can issue a new invitation only when the shared participation eligibility state is `eligible`. The backend remains authoritative; direct URL access cannot bypass the gate.

The member invitation page must be dynamic. Quota and expiry come from Settings; reward points come from the canonical `invite_member` reputation rule. The page must not hard-code 10 invitations, 72 hours, or an obsolete Bahar transfer reward. Expired/abandoned invitations do not permanently consume quota under the existing lifecycle rules.

If Najm Bahar is missing, the page explains the prerequisite and links to `najm-bahar.agreement`. If the account exists but membership fee is due, it links to `najm-bahar.dashboard` for payment. Eligible members see issuance controls and live quota statistics.

## Group chat gate
Reading an active group remains allowed exactly as before. The new gate affects only the message composer and the message-store endpoint.

If the member has no Najm Bahar account, the composer is replaced client-side by a clear action card linking to the Najm Bahar agreement. If the account exists but the fee is due, it is replaced by a card linking to the Najm Bahar dashboard/payment flow. Existing role/session/profile restrictions remain authoritative and are not broadened.

`MessageController::store()` independently rejects a non-eligible member before any message is persisted. This prevents bypass through direct HTTP/AJAX requests, voice/file submission, or manipulated JavaScript.

## Non-goals
This phase does not change voting, polls, posts, comments, reactions, elections, private chat, group membership, or unrelated Najm Bahar behavior. It does not redesign Group Chat or other layouts. No merge to `main` is permitted.
