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


# InvestmentService must use the explicit actor-to-actor investment boundary.
p = Path("app/Modules/NajmBahar/Services/InvestmentService.php")
s = p.read_text(encoding="utf-8")
s = s.replace("use App\\Modules\\NajmBahar\\Services\\TransactionService;\n", "")
s = s.replace(
    """    public function __construct(
        protected TransactionService $transactionService
    ) {}""",
    """    public function __construct(
        protected InvestmentTransferService $investmentTransferService
    ) {}""",
)
s = s.replace("$this->transactionService->transfer(", "$this->investmentTransferService->transfer(")
p.write_text(s, encoding="utf-8")

# Investment tests exercise the settlement domain, not the global pre-threshold
# lock. Make their Active-Bahar funding explicit and set a low threshold.
for path in [
    "tests/Unit/NajmBahar/InvestmentServiceTest.php",
    "tests/Feature/NajmBahar/InvestmentControllerTest.php",
]:
    p = Path(path)
    s = p.read_text(encoding="utf-8")
    if "use App\\Models\\Setting;" not in s:
        s = s.replace("use App\\Models\\User;\n", "use App\\Models\\User;\nuse App\\Models\\Setting;\n")

    # Every direct test funding assignment represents transferable Active Bahar.
    s = re.sub(
        r"(\$[A-Za-z]+Account->balance = ([0-9]+);[^\n]*\n)(?!\s*\$[A-Za-z]+Account->balance_active)",
        lambda m: m.group(1) + re.match(r"\$([A-Za-z]+)Account", m.group(1).lstrip()).group(0).replace("->balance", "->balance_active") + f" = {m.group(2)};\n",
        s,
    )

    # The regex above may preserve indentation poorly; normalize generated active lines.
    lines = s.splitlines()
    normalized = []
    for i, line in enumerate(lines):
        normalized.append(line)
        if "Account->balance = " in line and i + 1 < len(lines):
            next_line = lines[i + 1]
            if "Account->balance_active = " in next_line and not next_line.startswith(line[:len(line)-len(line.lstrip())]):
                pass
    s = "\n".join(lines) + ("\n" if p.read_text(encoding="utf-8").endswith("\n") else "")

    # Simpler deterministic normalization: after each balance assignment, ensure the same variable active bucket matches.
    pattern = re.compile(r"^(\s*)(\$[A-Za-z]+Account)->balance = ([0-9]+);([^\n]*)$", re.M)
    def active_repl(m):
        block = m.group(0)
        active = f"{m.group(1)}{m.group(2)}->balance_active = {m.group(3)};"
        tail_start = m.end()
        return block + "\n" + active
    # Remove any generated duplicate active lines first, then rebuild once.
    s = re.sub(r"^\s*\$[A-Za-z]+Account->balance_active = [0-9]+;\n", "", s, flags=re.M)
    s = pattern.sub(active_repl, s)

    # Add canonical setting once in setUp after account service resolution.
    anchor = "        $this->accountService = app(AccountService::class);\n"
    setting_block = """        $this->accountService = app(AccountService::class);

        Setting::query()->updateOrCreate(['id' => 1], [
            'najm_bahar_user_threshold' => 1,
        ]);
"""
    if anchor in s and setting_block not in s:
        s = s.replace(anchor, setting_block, 1)

    p.write_text(s, encoding="utf-8")

# One old ProjectService fixture still used visibility as project_type.
p = Path("tests/Unit/NajmBahar/ProjectServiceTest.php")
s = p.read_text(encoding="utf-8")
s = s.replace("            'project_type' => 'public',", "            'project_type' => 'production',\n            'project_visibility' => 'public',")
p.write_text(s, encoding="utf-8")

# The new explicit investment boundary is itself a reviewed financial persistence
# orchestrator; it does not mutate balances directly, so it should not need an
# architecture allowlist exception. Keep the boundary test unchanged here.
