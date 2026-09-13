const assert = require('assert');

function serializeFiltersObj(obj) {
    const parts = [];
    if (obj.medium) parts.push('m=' + encodeURIComponent(obj.medium));
    if (obj.genre) parts.push('g=' + encodeURIComponent(obj.genre));
    if (obj.sold) parts.push('s=' + encodeURIComponent(obj.sold));
    if (obj.prints) parts.push('p=1');
    if (obj.search) parts.push('q=' + encodeURIComponent(obj.search));
    return parts.join('&');
}

function deserializeFiltersFromQuery(query) {
    const q = new URLSearchParams(query.startsWith('?') ? query.slice(1) : query);
    const filters = { medium: null, genre: null, sold: null, prints: null, search: '' };
    if (q.has('m')) filters.medium = q.get('m');
    if (q.has('g')) filters.genre = q.get('g');
    if (q.has('s')) filters.sold = q.get('s');
    if (q.has('p')) filters.prints = true;
    if (q.has('q')) filters.search = q.get('q');
    return filters;
}

// Tests
(function run() {
    // serialize basic
    const s1 = serializeFiltersObj({ medium: 'Pastel', genre: 'Landscape', sold: 'available', prints: true, search: 'flower' });
    assert.ok(s1.includes('m=Pastel'));
    assert.ok(s1.includes('g=Landscape'));
    assert.ok(s1.includes('s=available'));
    assert.ok(s1.includes('p=1'));
    assert.ok(s1.includes('q=flower'));

    // deserialize basic
    const f = deserializeFiltersFromQuery('m=Pastel&g=Landscape&s=available&p=1&q=flower');
    assert.strictEqual(f.medium, 'Pastel');
    assert.strictEqual(f.genre, 'Landscape');
    assert.strictEqual(f.sold, 'available');
    assert.strictEqual(f.prints, true);
    assert.strictEqual(f.search, 'flower');

    // empty
    const empty = deserializeFiltersFromQuery('');
    assert.strictEqual(empty.medium, null);
    assert.strictEqual(empty.search, '');

    console.log('All filter serialization tests passed');
})();
