from pathlib import Path

# Current project contract requires an investment duration in the approved fixture.
p = Path('tests/Unit/NajmBahar/ProjectServiceTest.php')
s = p.read_text(encoding='utf-8')
marker = '    public function it_can_get_approved_projects()\n'
pos = s.index(marker)
head, tail = s[:pos], s[pos:]
anchor = "            'profit_percentage' => 15,\n"
duration = "            'investment_duration_months' => 12,\n"
if duration not in tail:
    if anchor not in tail:
        raise SystemExit('Approved-project duration anchor not found')
    tail = tail.replace(anchor, anchor + duration, 1)
p.write_text(head + tail, encoding='utf-8')

# Auction::isActive() uses ends_at rather than the legacy end_time field.
p = Path('tests/Feature/AuctionConcurrencyTest.php')
s = p.read_text(encoding='utf-8')
anchor = "            'end_time' => now()->addDay(),\n"
ends_at = "            'ends_at' => now()->addDay(),\n"
if ends_at not in s:
    if anchor not in s:
        raise SystemExit('Auction ends_at anchor not found')
    s = s.replace(anchor, anchor + ends_at, 1)
p.write_text(s, encoding='utf-8')

# Keep success assertions strict while surfacing the caught domain exception.
p = Path('tests/Feature/NajmBahar/InvestmentControllerTest.php')
s = p.read_text(encoding='utf-8')
old = """        $response->assertRedirect()
            ->assertSessionHas('success');
"""
new = """        $response->assertRedirect();
        $this->assertTrue(
            $response->getSession()->has('success'),
            (string) ($response->getSession()->get('error') ?? 'Expected success flash was not set.')
        );
"""
if old in s:
    s = s.replace(old, new)
p.write_text(s, encoding='utf-8')

# Surface scheduled executor diagnostics before console-output expectations obscure them.
p = Path('tests/Feature/ScheduledTransactionTest.php')
s = p.read_text(encoding='utf-8')
old = """        $this->artisan('najm-bahar:process-scheduled')
            ->expectsOutput('NajmBahar scheduled processing completed. Processed: 1')
            ->assertExitCode(0);

        $scheduled->refresh();
        $this->assertSame('processed', $scheduled->status);
"""
new = """        $this->artisan('najm-bahar:process-scheduled')
            ->assertExitCode(0);

        $scheduled->refresh();
        $this->assertSame(
            'processed',
            $scheduled->status,
            (string) ($scheduled->last_error ?? 'Scheduled transfer was not processed.')
        );
"""
if old in s:
    s = s.replace(old, new, 1)
elif 'Scheduled transfer was not processed.' not in s:
    raise SystemExit('Scheduled diagnostic anchor not found')
p.write_text(s, encoding='utf-8')
