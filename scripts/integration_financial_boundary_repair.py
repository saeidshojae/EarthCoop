from pathlib import Path
import re


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

# Investment tests exercise settlement itself, not the global pre-threshold
# lock. Their directly assigned funds therefore represent transferable Active
# Bahar and the threshold is explicitly lowered only inside these test cases.
for path in [
    "tests/Unit/NajmBahar/InvestmentServiceTest.php",
    "tests/Feature/NajmBahar/InvestmentControllerTest.php",
]:
    p = Path(path)
    s = p.read_text(encoding="utf-8")

    if "use App\\Models\\Setting;" not in s:
        s = s.replace("use App\\Models\\User;\n", "use App\\Models\\User;\nuse App\\Models\\Setting;\n")

    # Remove malformed lines produced by the first repair iteration.
    s = re.sub(r"^\s*\$[A-Za-z]+Account = [0-9]+;\n", "", s, flags=re.M)

    # For every direct main-account funding assignment, mirror that amount into
    # balance_active exactly once. Existing active lines are replaced rather
    # than duplicated, making this operation idempotent.
    lines = s.splitlines()
    out = []
    i = 0
    balance_pattern = re.compile(r"^(\s*)(\$[A-Za-z]+Account)->balance = ([0-9]+);(.*)$")
    active_pattern = re.compile(r"^\s*\$[A-Za-z]+Account->balance_active = [0-9]+;\s*$")
    while i < len(lines):
        line = lines[i]
        out.append(line)
        match = balance_pattern.match(line)
        if match:
            active_line = f"{match.group(1)}{match.group(2)}->balance_active = {match.group(3)};"
            if i + 1 < len(lines) and active_pattern.match(lines[i + 1]):
                i += 1
            out.append(active_line)
        i += 1
    s = "\n".join(out) + "\n"

    anchor = "        $this->accountService = app(AccountService::class);\n"
    setting_block = """        $this->accountService = app(AccountService::class);

        Setting::query()->updateOrCreate(['id' => 1], [
            'najm_bahar_user_threshold' => 1,
        ]);
"""
    if setting_block not in s and anchor in s:
        s = s.replace(anchor, setting_block, 1)

    p.write_text(s, encoding="utf-8")

# One old ProjectService fixture still used visibility as project_type.
p = Path("tests/Unit/NajmBahar/ProjectServiceTest.php")
s = p.read_text(encoding="utf-8")
s = s.replace(
    "            'project_type' => 'public',",
    "            'project_type' => 'production',\n            'project_visibility' => 'public',",
)
p.write_text(s, encoding="utf-8")
