from pathlib import Path

p = Path('resources/views/welcome.blade.php')
s = p.read_text(encoding='utf-8')
old = '@if($setting->invation_status == 1)'
new = '@if(($setting?->invation_status ?? 0) == 1)'
if old in s:
    s = s.replace(old, new, 1)
elif new not in s:
    raise SystemExit('Expected welcome invitation setting condition not found')
p.write_text(s, encoding='utf-8')
