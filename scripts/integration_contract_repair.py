from pathlib import Path
import re


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    s = p.read_text(encoding="utf-8")
    if new in s:
        return
    if old not in s:
        raise SystemExit(f"Expected pattern not found in {path}: {old!r}")
    p.write_text(s.replace(old, new, 1), encoding="utf-8")


def replace_all(path: str, old: str, new: str) -> None:
    p = Path(path)
    s = p.read_text(encoding="utf-8")
    if old not in s:
        return
    p.write_text(s.replace(old, new), encoding="utf-8")


# Governance eligibility snapshots intentionally exclude system identities.
cohort_old = """        $expectedEligible = GroupUser::where('group_id', $group->id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->count();"""
cohort_new = """        $expectedEligible = GroupUser::query()
            ->join('users', 'users.id', '=', 'group_user.user_id')
            ->where('group_user.group_id', $group->id)
            ->where('group_user.status', 1)
            ->whereNull('group_user.deleted_at')
            ->where('users.is_system', false)
            ->count();"""
replace_once(
    "tests/Feature/Governance/ProfessionalReferralAndEligibilitySnapshotTest.php",
    cohort_old,
    cohort_new,
)
replace_once(
    "tests/Feature/Governance/ProposalLifecycleServiceTest.php",
    cohort_old,
    cohort_new,
)

current_old = """        $currentEligibleAfterMembershipChange = GroupUser::where('group_id', $group->id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->count();"""
current_new = """        $currentEligibleAfterMembershipChange = GroupUser::query()
            ->join('users', 'users.id', '=', 'group_user.user_id')
            ->where('group_user.group_id', $group->id)
            ->where('group_user.status', 1)
            ->whereNull('group_user.deleted_at')
            ->where('users.is_system', false)
            ->count();"""
replace_once(
    "tests/Feature/Governance/ProfessionalReferralAndEligibilitySnapshotTest.php",
    current_old,
    current_new,
)

# Investment fixtures predate the project_type/project_visibility split and
# the current submission invariants. Keep them as capital-participation cases.
legacy_capital = "            'project_type' => 'public',"
canonical_capital = """            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'نیاز اقتصادی روشن برای پروژه',
            'solution_description' => 'راه‌حل اجرایی پروژه',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,"""
replace_all(
    "tests/Feature/NajmBahar/InvestmentControllerTest.php",
    legacy_capital,
    canonical_capital,
)
replace_all(
    "tests/Unit/NajmBahar/InvestmentServiceTest.php",
    legacy_capital,
    canonical_capital,
)

# Project controller/service fixtures provide auction valuation/share fields;
# explicitly select auction_shares and provide canonical target market.
for path in [
    "tests/Feature/NajmBahar/ProjectControllerTest.php",
    "tests/Unit/NajmBahar/ProjectServiceTest.php",
]:
    p = Path(path)
    s = p.read_text(encoding="utf-8")
    s = re.sub(
        r"(\s*'project_stage'\s*=>\s*'[^']+',\n)(?!\s*'investment_method')",
        r"\1            'investment_method' => 'auction_shares',\n",
        s,
    )
    s = re.sub(
        r"(\s*'solution_description'\s*=>\s*'[^']*',\n)(?!\s*'target_market')",
        r"\1            'target_market' => 'general',\n",
        s,
    )
    p.write_text(s, encoding="utf-8")

# The invalid-project test is specifically an invalid auction valuation test.
p = Path("tests/Feature/NajmBahar/ProjectControllerTest.php")
s = p.read_text(encoding="utf-8")
invalid_old = """            'status' => 'draft',
            'base_value_min' => 0, // مقدار نامعتبر
            'base_value_max' => 0, // مقدار نامعتبر"""
invalid_new = """            'status' => 'draft',
            'investment_method' => 'auction_shares',
            'problem_statement' => 'مسئله',
            'solution_description' => 'راه‌حل',
            'accept_transparency' => true,
            'accept_rules' => true,
            'base_value_min' => 0, // مقدار نامعتبر
            'base_value_max' => 0, // مقدار نامعتبر"""
if invalid_old in s:
    p.write_text(s.replace(invalid_old, invalid_new, 1), encoding="utf-8")

# Main intentionally preserves any already-loaded jQuery plugin instance.
replace_once(
    "tests/js/group-chat/source-contract.test.js",
    r"assert.match(app, /installSelect2\(window, \$\)/);",
    r"assert.match(app, /installSelect2\(window, appJQuery\)/);",
)

# Release B/C introduced dedicated mutation/persistence services. Keep the
# transitional architecture boundary explicit rather than allowing arbitrary callers.
p = Path("tests/Architecture/NajmBaharFinancialMutationBoundaryTest.php")
s = p.read_text(encoding="utf-8")
marker = "        'app/Modules/NajmBahar/Services/AccountService.php',\n"
additions = """        // Dedicated Release B/C financial persistence boundaries.
        'app/Modules/NajmBahar/Services/PublicExecutionPaymentService.php',
        'app/Modules/NajmBahar/Services/CrossOwnerActiveSubAccountTransferService.php',
        'app/Modules/NajmBahar/Services/MainAccountSystemTransferService.php',
        'app/Modules/NajmBahar/Services/AccountInvariantService.php',
        'app/Modules/NajmBahar/Services/ScheduledSubAccountTransferExecutor.php',
        'app/Modules/NajmBahar/Services/PublicExecutionReversalService.php',
        'app/Modules/NajmBahar/Services/DimCommitmentService.php',
        'app/Modules/NajmBahar/Services/InternalSubAccountTransferService.php',
        'app/Modules/NajmBahar/Services/SubAccountSystemTransferService.php',
"""
if additions not in s:
    if marker not in s:
        raise SystemExit("Architecture allowlist insertion point not found")
    p.write_text(s.replace(marker, marker + additions, 1), encoding="utf-8")

# Finally make integration CI include the entire PHPUnit suite, not only
# focused module regressions. This is the safety net for all main-branch work.
p = Path(".github/workflows/integration-full-validation.yml")
s = p.read_text(encoding="utf-8")
needle = """          echo '=== GROUP CHAT JAVASCRIPT ==='
          npm run test:group-chat || status=1

          exit $status"""
replacement = """          echo '=== GROUP CHAT JAVASCRIPT ==='
          npm run test:group-chat || status=1

          echo '=== FULL PROJECT PHPUNIT REGRESSION ==='
          php vendor/bin/phpunit --configuration phpunit.xml.dist || status=1

          exit $status"""
if "=== FULL PROJECT PHPUNIT REGRESSION ===" not in s:
    if needle not in s:
        raise SystemExit("Full-validation insertion point not found")
    p.write_text(s.replace(needle, replacement, 1), encoding="utf-8")
