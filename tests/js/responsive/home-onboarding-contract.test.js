import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

const home = read('resources/views/home.blade.php');
const step1 = read('resources/views/auth/register_step1.blade.php');
const step2 = read('resources/views/auth/register_step2.blade.php');

test('Home explicitly inherits the Welcome/Register visual language instead of inventing a new one', () => {
    for (const source of [step1, step2]) {
        assert.match(source, /--color-earth-green:\s*#10b981/);
        assert.match(source, /--color-ocean-blue:\s*#3b82f6/);
        assert.match(source, /--color-digital-gold:\s*#f59e0b/);
        assert.match(source, /linear-gradient\(145deg,\s*var\(--color-pure-white\)\s*0%,\s*#f0f4f7\s*100%\)/);
        assert.match(source, /linear-gradient\(90deg,\s*var\(--color-earth-green\),\s*var\(--color-ocean-blue\),\s*var\(--color-digital-gold\)\)/);
    }

    assert.match(home, /data-home-identity-surface/);
    assert.match(home, /home-hero::before/);
    assert.match(home, /linear-gradient\(90deg,\s*var\(--color-earth-green\),\s*var\(--color-ocean-blue\),\s*var\(--color-digital-gold\)\)/);
    assert.match(home, /linear-gradient\(145deg,\s*var\(--color-pure-white\)\s*0%,\s*#f0f4f7\s*100%\)/);
});

test('Home exposes a clear onboarding journey without replacing the three canonical group cards', () => {
    assert.match(home, /data-home-journey/);
    assert.match(home, /مسیر من در ارث‌کوپ/);
    assert.match(home, /مکان و حکمرانی/);
    assert.match(home, /نجم بهار/);
    assert.match(home, /گروه‌های من/);
    assert.match(home, /دعوت و مشارکت/);

    assert.match(home, /route\('groups\.index',\s*\['tab'\s*=>\s*'public'\]\)/);
    assert.match(home, /route\('groups\.index',\s*\['tab'\s*=>\s*'specialty'\]\)/);
    assert.match(home, /route\('groups\.index',\s*\['tab'\s*=>\s*'exclusive'\]\)/);
});

test('Home button, card and typography hierarchy is reusable and restrained', () => {
    assert.match(home, /\.home-primary-action\s*\{/);
    assert.match(home, /\.home-secondary-action\s*\{/);
    assert.match(home, /\.home-interactive-card\s*\{/);
    assert.match(home, /\.home-section-title\s*\{/);
    assert.match(home, /\.home-eyebrow\s*\{/);

    assert.doesNotMatch(home, /#9333ea/);
    assert.match(home, /transition:[^;}]*(?:transform|box-shadow)[^;}]*(?:180ms|\.18s|200ms|\.2s|250ms|\.25s|300ms|\.3s)/);
});

test('Home has distinct mobile and desktop composition rules and does not rely on horizontal scrolling', () => {
    assert.match(home, /\.home-journey-grid\s*\{[^}]*grid-template-columns:\s*repeat\(4,\s*minmax\(0,\s*1fr\)\)/s);
    assert.match(home, /@media\s*\(max-width:\s*767\.98px\)[\s\S]*?\.home-journey-grid\s*\{[^}]*grid-template-columns:\s*1fr/s);
    assert.match(home, /@media\s*\(min-width:\s*768px\)[\s\S]*?\.home-hero-layout/s);
    assert.doesNotMatch(home, /\.home-(?:journey|groups|actions)[^{]*\{[^}]*overflow-x:\s*auto/s);
});

test('Home motion respects reduced-motion and touch targets remain usable', () => {
    assert.match(home, /@media\s*\(prefers-reduced-motion:\s*reduce\)/);
    assert.match(home, /\.home-primary-action[^}]*min-height:\s*(?:44|46|48)px/s);
    assert.match(home, /\.home-secondary-action[^}]*min-height:\s*(?:44|46|48)px/s);
});

test('Home preserves admin-managed slider and content inside the redesigned hierarchy', () => {
    assert.match(home, /\$homeSliders->isNotEmpty\(\)/);
    assert.match(home, /\$homeSetting\?->home_content/);
    assert.match(home, /data-home-admin-content/);
});

test('Home consumes the civic dashboard read model for journey, today and next action presentation', () => {
    assert.match(home, /\$homeDashboard\[['"]journey['"]\]/);
    assert.match(home, /\$homeDashboard\[['"]today['"]\]/);
    assert.match(home, /\$homeDashboard\[['"]next_action['"]\]/);
    assert.match(home, /data-home-today/);
    assert.match(home, /data-home-today-zero-state/);
    assert.match(home, /data-home-next-action/);
    assert.match(home, /route\(\s*\$nextAction\[['"]route['"]\]\s*\)/);
    assert.doesNotMatch(home, /AccountService::class|CurrentElectionCenterService::class|Poll::(?:query|where)/);
});

test('Home reserves the tri-color identity stripe for the Hero and demotes secondary content', () => {
    assert.match(home, /\.home-hero::before\s*\{/);
    assert.doesNotMatch(home, /\.home-identity-surface::before\s*\{/);
    assert.match(home, /\.home-secondary-surface\s*\{/);

    const journey = home.indexOf('data-home-journey');
    const today = home.indexOf('data-home-today');
    const groups = home.indexOf('data-home-groups');
    const admin = home.indexOf('data-home-admin-content');

    assert.ok(journey !== -1 && today !== -1 && groups !== -1 && admin !== -1);
    assert.ok(journey < today, 'journey should precede today');
    assert.ok(today < groups, 'today should precede groups');
    assert.ok(groups < admin, 'admin/news content should follow personal surfaces');
});


test('Home promotes the docs participation CTA and managed slider before the journey', () => {
    assert.match(home, /ارث‌کوپ را بفهمید و مشارکت کنید/);
    assert.match(home, /home-docs-action/);
    assert.match(home, /data-home-priority-slider/);

    const hero = home.indexOf('data-home-identity-surface');
    const slider = home.indexOf('data-home-priority-slider');
    const journey = home.indexOf('data-home-journey');

    assert.ok(hero !== -1 && slider !== -1 && journey !== -1);
    assert.ok(hero < slider, 'priority slider should follow the hero');
    assert.ok(slider < journey, 'priority slider should precede the onboarding journey');
});
