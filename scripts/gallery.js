
/* ---------------------------------------------------------
   GLOBAL STATE
--------------------------------------------------------- */
let items = [];
let exhibitions = [];
let currentFilters = {
    medium: null,
    sold: null,
    genre: null,
    collection: null,
    prints: null,
    search: ""
};

/* ---------------------------------------------------------
   AUTO-GENERATION HELPERS (with override support)
--------------------------------------------------------- */
function fileSlug(slug) {
    return slug.replace(/-/g, "");
}

function getTitle(item) {
    if (item.title && item.title.trim() !== "") {
        return item.title;
    }
    return item.slug
        .split('-')
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function getThumb(item) {
    if (item.thumbnail && item.thumbnail.trim() !== "") {
        return item.thumbnail;
    }
    return `/gallery/all/thumbs/${fileSlug(item.slug)}thumb.jpg`;
}

function getFull(item) {
    if (item.full && item.full.trim() !== "") {
        return item.full;
    }
    return `/gallery/all/full/${fileSlug(item.slug)}.jpg`;
}

function getPage(item) {
    if (item.page && item.page.trim() !== "") {
        return item.page;
    }
    return `/gallery/work.html#${item.slug}`;
}

/* ---------------------------------------------------------
   LOAD JSON DATA
--------------------------------------------------------- */
function normalizeUploadedItem(item) {
    if (!item || !item.slug) return null;

    return {
        ...item,
        title: item.title || "",
        medium: item.medium || "",
        genre: item.genre || "",
        printsAvailable: Boolean(item.printsAvailable || item.prints_available || item.prints),
        description: item.description || "",
        thumbnail: item.thumbnail || item.thumbnail_path || "",
        full: item.full || item.full_path || item.thumbnail || item.thumbnail_path || "",
        dateAdded: item.dateAdded || item.date_added || "",
        disabled: Boolean(item.disabled),
        sold: Boolean(item.sold),
        source: item.source || "server"
    };
}

async function getUploadedGalleryItems() {
    try {
        const response = await fetch("/gallery-api.php?action=list", {
            headers: { Accept: "application/json" },
            credentials: "same-origin"
        });

        if (!response.ok) {
            throw new Error("Gallery API unavailable");
        }

        const payload = await response.json();
        const items = Array.isArray(payload) ? payload : [];
        return items.map(normalizeUploadedItem).filter(Boolean);
    } catch (error) {
        return [];
    }
}

async function loadData() {
    if (items.length && exhibitions.length) return;

    const uploadedItems = await getUploadedGalleryItems();
    const exhibitionData = await fetch("/gallery-api.php?action=list-exhibitions", {
        headers: { Accept: "application/json" },
        credentials: "same-origin"
    }).then(r => r.ok ? r.json() : []);

    exhibitions = Array.isArray(exhibitionData) ? exhibitionData : [];
    exhibitions.sort((a, b) => new Date(b.createdAt || 0) - new Date(a.createdAt || 0));
    items = uploadedItems.sort((a, b) => {
        const dateA = a.artworkCreatedAt || a.dateAdded || a.createdAt || 0;
        const dateB = b.artworkCreatedAt || b.dateAdded || b.createdAt || 0;
        return new Date(dateB) - new Date(dateA);
    });
}

function renderAppliedFiltersAndBreadcrumb() {
    const container = document.getElementById('applied-filters');
    const breadcrumb = document.getElementById('filter-breadcrumb');
    if (!container) return;

    container.innerHTML = '';
    const parts = [];

    // hide the textual breadcrumb — we use interactive badges instead
    if (breadcrumb) breadcrumb.style.display = 'none';

    const addBadge = (label, key, value) => {
        const badge = document.createElement('span');
        badge.className = 'filter-badge';
        badge.textContent = label;

        const rem = document.createElement('span');
        rem.className = 'remove';
        rem.setAttribute('role', 'button');
        rem.setAttribute('tabindex', '0');
        rem.setAttribute('aria-label', `Remove filter ${label}`);
        rem.dataset.filter = key;
        rem.dataset.value = value || '';
        rem.textContent = '×';

        rem.addEventListener('click', () => removeFilter(key));
        rem.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                removeFilter(key);
            }
        });

        badge.appendChild(rem);
        container.appendChild(badge);
    };

    if (currentFilters.medium) {
        const label = `Medium: ${currentFilters.medium}`;
        parts.push(label);
        addBadge(label, 'medium', currentFilters.medium);
    }
    if (currentFilters.collection) {
        const label = `Collection: ${currentFilters.collection}`;
        parts.push(label);
        addBadge(label, 'collection', currentFilters.collection);
    }
    if (currentFilters.genre) {
        const label = `Genre: ${currentFilters.genre}`;
        parts.push(label);
        addBadge(label, 'genre', currentFilters.genre);
    }
    if (currentFilters.sold) {
        const label = currentFilters.sold === 'sold' ? 'Sold' : 'Available';
        parts.push(label);
        addBadge(label, 'sold', currentFilters.sold);
    }
    if (currentFilters.prints) {
        const label = 'Limited Prints Available';
        parts.push(label);
        addBadge(label, 'prints', String(currentFilters.prints));
    }
    if (currentFilters.search && currentFilters.search.trim() !== '') {
        const label = `Search: ${currentFilters.search}`;
        parts.push(label);
        addBadge(label, 'search', currentFilters.search);
    }

    // Keep textual breadcrumb in sync with badges
    breadcrumb.innerText = parts.join(' | ');
}

function removeFilter(key) {
    if (!key) return;

    try {
        if (key === 'medium') {
            currentFilters.medium = null;
            const el = document.getElementById('filter-medium'); if (el) el.value = '';
        } else if (key === 'genre') {
            currentFilters.genre = null;
            const el = document.getElementById('filter-genre'); if (el) el.value = '';
        } else if (key === 'sold') {
            currentFilters.sold = null;
            const el = document.getElementById('filter-sold'); if (el) el.value = '';
        } else if (key === 'prints') {
            currentFilters.prints = null;
            const el = document.getElementById('filter-prints'); if (el) el.checked = false;
        } else if (key === 'search') {
            currentFilters.search = '';
            const el = document.getElementById('filter-search'); if (el) el.value = '';
        }
    } catch (e) { /* ignore missing elements */ }

    updateURLWithFilters();
    loadGallery();
}

/* ---------------------------------------------------------
   Populate Filters Dynamically
--------------------------------------------------------- */	
function populateMediumFilter() {
    const select = document.getElementById("filter-medium");
    select.querySelectorAll("option:not(:first-child)").forEach(option => option.remove());

    // Extract unique mediums
    const mediums = [...new Set(items.map(i => i.medium).filter(Boolean))];

    // Add them to the dropdown
    mediums.sort().forEach(m => {
        const opt = document.createElement("option");
        opt.value = m;
        opt.textContent = m;
        select.appendChild(opt);
    });
}

function populateGenreFilter() {
    const select = document.getElementById("filter-genre");
    select.querySelectorAll("option:not(:first-child)").forEach(option => option.remove());

    // Extract unique genres
    const genres = [...new Set(items.map(i => i.genre).filter(Boolean))];

    genres.sort().forEach(g => {
        const opt = document.createElement("option");
        opt.value = g;
        opt.textContent = g;
        select.appendChild(opt);
    });
}
	

/* ---------------------------------------------------------
   FILTER LOGIC (shared)
--------------------------------------------------------- */
function applyFilters(list, filters) {
    let filtered = list;

    if (filters.medium) {
        filtered = filtered.filter(i => i.medium === filters.medium);
    }
		
    if (filters.genre) {
        filtered = filtered.filter(i => i.genre === filters.genre);
    }

    if (filters.collection) {
        filtered = filtered.filter(i => (i.collection || '').toLowerCase() === (String(filters.collection) || '').toLowerCase());
    }

    if (filters.prints) {
        if (filters.prints === true || filters.prints === "true") {
            filtered = filtered.filter(i => i.printsAvailable === true);
        } else if (filters.prints === "false") {
            filtered = filtered.filter(i => !i.printsAvailable);
        }
    }
	
    if (filters.sold) {
        if (filters.sold === "sold") {
            filtered = filtered.filter(i => i.sold === true);
        } else if (filters.sold === "available") {
            filtered = filtered.filter(i => i.sold !== true);
        }
    }

    if (filters.search && filters.search.trim() !== "") {
        const q = filters.search.toLowerCase();
        filtered = filtered.filter(i =>
            getTitle(i).toLowerCase().includes(q) ||
            (i.description || "").toLowerCase().includes(q)
        );
    }

    return filtered;
}

/* ---------------------------------------------------------
   ROUTER
--------------------------------------------------------- */
async function loadGallery() {
    await loadData();

    // read filters from URL (query/hash) and merge into currentFilters
    readFiltersFromURL();

    populateMediumFilter();
    populateGenreFilter();

    // Sync dropdowns/selected UI based on currentFilters
    try {
        if (currentFilters.medium) document.getElementById('filter-medium').value = currentFilters.medium;
        if (currentFilters.genre) document.getElementById('filter-genre').value = currentFilters.genre;
        if (currentFilters.sold) document.getElementById('filter-sold').value = currentFilters.sold;
        if (currentFilters.search) document.getElementById('filter-search').value = currentFilters.search;
        if (typeof currentFilters.prints !== 'undefined' && document.getElementById('filter-prints')) document.getElementById('filter-prints').checked = Boolean(currentFilters.prints === true || currentFilters.prints === 'true');
    } catch (e) { /* ignore if elements missing */ }

    // Toggle selected visual class
    try {
        const m = document.getElementById('filter-medium'); if (m) { if (currentFilters.medium) m.classList.add('filter-selected'); else m.classList.remove('filter-selected'); }
        const g = document.getElementById('filter-genre'); if (g) { if (currentFilters.genre) g.classList.add('filter-selected'); else g.classList.remove('filter-selected'); }
        const s = document.getElementById('filter-sold'); if (s) { if (currentFilters.sold) s.classList.add('filter-selected'); else s.classList.remove('filter-selected'); }
    } catch (e) {}

    renderAppliedFiltersAndBreadcrumb();

	const hash = window.location.hash.replace("#", "");

	if (hash.startsWith("tag-")) {
		loadTagMode(hash.replace("tag-", ""), currentFilters);

	} else if (hash.startsWith("genre-")) {
		loadGenreMode(hash.replace("genre-", ""), currentFilters);

	} else if (hash.startsWith("medium-")) {
		loadMediumMode(hash.replace("medium-", ""), currentFilters);

	} else if (hash.startsWith("date-")) {
		loadDateMode(hash.replace("date-", ""), currentFilters);

    } else if (hash.startsWith("collection-")) {
        loadCollectionMode(hash.replace("collection-", ""), currentFilters);

	} else if (hash.startsWith("disabled-")) {
		loadDisabledMode(currentFilters);

	} else if (hash) {
		loadExhibitionMode(hash, currentFilters);

	} else {
		loadGalleryMode(currentFilters);
	}
}

/* ---------------------------------------------------------
   URL serialization helpers
--------------------------------------------------------- */
function serializeFilters() {
    const parts = [];
    if (currentFilters.medium) parts.push('m=' + encodeURIComponent(currentFilters.medium));
    if (currentFilters.genre) parts.push('g=' + encodeURIComponent(currentFilters.genre));
    if (currentFilters.collection) parts.push('c=' + encodeURIComponent(currentFilters.collection));
    if (currentFilters.sold) parts.push('s=' + encodeURIComponent(currentFilters.sold));
    if (currentFilters.prints) parts.push('p=1');
    if (currentFilters.search) parts.push('q=' + encodeURIComponent(currentFilters.search));
    return parts.join('&');
}

function deserializeFiltersFromQuery(query) {
    const q = new URLSearchParams(query);
    const filters = { medium: null, genre: null, collection: null, sold: null, prints: null, search: '' };
    if (q.has('m')) filters.medium = q.get('m');
    if (q.has('g')) filters.genre = q.get('g');
    if (q.has('c')) filters.collection = q.get('c');
    if (q.has('s')) filters.sold = q.get('s');
    if (q.has('p')) filters.prints = true;
    if (q.has('q')) filters.search = q.get('q');
    return filters;
}

function updateURLWithFilters() {
    const serialized = serializeFilters();
    const url = new URL(window.location.href);
    if (serialized) {
        url.search = serialized;
    } else {
        url.search = '';
    }
    history.replaceState(null, '', url.toString());
}

function readFiltersFromURL() {
    // Priority: query string, then hash 'filters:', then legacy hash modes
    if (window.location.search && window.location.search.length > 1) {
        const f = deserializeFiltersFromQuery(window.location.search);
        Object.assign(currentFilters, f);
        return;
    }
    const h = (window.location.hash || '').replace('#','');
    if (h.startsWith('filters:')) {
        const q = h.replace('filters:','');
        const parsed = new URLSearchParams(q);
        const f = deserializeFiltersFromQuery(parsed.toString());
        Object.assign(currentFilters, f);
        return;
    }
    // legacy hash modes
    const hash = h;
    if (hash.startsWith('genre-')) {
        currentFilters.genre = decodeURIComponent(hash.replace('genre-','')) || null;
    } else if (hash.startsWith('medium-')) {
        currentFilters.medium = decodeURIComponent(hash.replace('medium-','')) || null;
    } else if (hash.startsWith('prints-')) {
        currentFilters.prints = hash.indexOf('true') !== -1;
    } else if (hash.startsWith('collection-')) {
        currentFilters.collection = decodeURIComponent(hash.replace('collection-','')) || null;
    } else if (hash.startsWith('available-')) {
        // legacy available-true/false hash → map to sold filter
        const val = hash.replace('available-','');
        if (val.indexOf('true') !== -1) currentFilters.sold = 'available';
        else currentFilters.sold = 'sold';
    }
}

/* ---------------------------------------------------------
   GALLERY MODE
--------------------------------------------------------- */
function loadGalleryMode(filters) {
    const exList = document.getElementById("exhibitions-list");
    const worksList = document.getElementById("works-list");
	
	document.getElementById("works-heading").innerText = "Individual Works";

    document.getElementById("page-title").innerText = "Gallery";
    document.getElementById("exhibition-header").style.display = "none";

    document.getElementById("exhibitions-heading").style.display = "block";
    document.getElementById("works-heading").style.display = "block";

    exList.innerHTML = "";
    worksList.innerHTML = "";

    const exData = exhibitions.map(ex => ({
        link: `gallery.html#${ex.slug}`,
        thumb: ex.thumbnailImage || ex.heroImage || "",
        text: `${ex.title || ex.name}<br>${ex.startDate || ""}`
    }));

    let exPageSize = 4;
    let exCurrentPage = 1;

    function renderExPage() {
        exList.innerHTML = "";
        const start = (exCurrentPage - 1) * exPageSize;
        const pageItems = exData.slice(start, start + exPageSize);

        pageItems.forEach(item => {
            exList.insertAdjacentHTML("beforeend", `
                <li>
                    <a href="${item.link}">
                        <img src="${item.thumb}">
                        <div><span>${item.text}</span></div>
                    </a>
                </li>
            `);
        });

        $('#exhibitions-list > li').each(function() { $(this).hoverdir(); });
        renderExPagination();
    }

    function renderExPagination() {
        const totalPages = Math.ceil(exData.length / exPageSize);
        const pagination = document.getElementById("pagination-exhibitions");
        pagination.innerHTML = "";

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            pagination.insertAdjacentHTML("beforeend", `
                <span class="${i === exCurrentPage ? 'active' : ''}"
                      onclick="changeExPage(${i})">${i}</span>
            `);
        }
    }

    window.changeExPage = function(page) {
        exCurrentPage = page;
        renderExPage();
    };

    renderExPage();

    let worksData = applyFilters(items, filters).map(item => ({
        link: getPage(item),
        thumb: getThumb(item),
        text: `${getTitle(item)}<br>${item.medium || ""}${item.price ? `<br>${item.price}` : ""}`
    }));

    let worksPageSize = 16;
    let worksCurrentPage = 1;

    function renderWorksPage() {
        worksList.innerHTML = "";
        const start = (worksCurrentPage - 1) * worksPageSize;
        const pageItems = worksData.slice(start, start + worksPageSize);

        pageItems.forEach(item => {
            worksList.insertAdjacentHTML("beforeend", `
                <li>
                    <a href="${item.link}">
                        <img src="${item.thumb}">
                        <div><span>${item.text}</span></div>
                    </a>
                </li>
            `);
        });

        $('#works-list > li').each(function() { $(this).hoverdir(); });
        renderWorksPagination();
    }

    function renderWorksPagination() {
        const totalPages = Math.ceil(worksData.length / worksPageSize);
        const pagination = document.getElementById("pagination-works");
        pagination.innerHTML = "";

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            pagination.insertAdjacentHTML("beforeend", `
                <span class="${i === worksCurrentPage ? 'active' : ''}"
                      onclick="changeWorksPage(${i})">${i}</span>
            `);
        }
    }

    window.changeWorksPage = function(page) {
        worksCurrentPage = page;
        renderWorksPage();
    };

    renderWorksPage();
}

/* ---------------------------------------------------------
   EXHIBITION MODE
--------------------------------------------------------- */
function loadExhibitionMode(tag, filters) {
    const ex = exhibitions.find(e => e.slug === tag);
    const worksList = document.getElementById("works-list");

    //
    // --- Exhibition metadata ---
    //

    // Title override or fallback
    const title = ex.title || ex.name || tag;
	document.getElementById("page-title").innerText = title;
    document.getElementById("ex-title").innerText = title;
	// Change “Individual Works” → “<Exhibition> Works”
	document.getElementById("works-heading").innerText = `${title} Works`;

    // Date
    document.getElementById("ex-meta").innerText =
        [ex.startDate, ex.endDate].filter(Boolean).join(" - ");

    // Location (optional)
    if (ex.location) {
        document.getElementById("ex-meta").innerText +=
            `  •  ${ex.location}`;
    }

    // Description
    document.getElementById("ex-description").innerText =
        ex.description || "";

    // Use the database-backed exhibition image when one is configured.
    const hero = ex.heroImage || "";

    // Show hero image under date
    document.getElementById("ex-hero").innerHTML = hero
        ? `<img src="${hero}" alt="${title}" style="max-width:100%;margin:20px 0;">`
        : "";

    //
    // --- UI visibility ---
    //
    document.getElementById("exhibition-header").style.display = "block";
    document.getElementById("exhibitions-heading").style.display = "none";
    document.getElementById("works-heading").style.display = "block";

    document.getElementById("pagination-exhibitions").innerHTML = "";
    document.getElementById("exhibitions-list").innerHTML = "";
    worksList.innerHTML = "";

    //
    // --- Filter works belonging to this exhibition ---
    //
    let filtered = items.filter(i =>
        Array.isArray(i.exhibitions) && i.exhibitions.includes(tag)
    );
	


    //
    // --- Apply shared filters (medium, sold, search, genre, etc.) ---
    //
    filtered = applyFilters(filtered, filters);
	
	// ⭐ NOW filtered exists — safe to update heading
	document.getElementById("works-heading").innerText =
    `${title} Works (${filtered.length})`;

    //
    // --- Paging (same as your existing exhibition paging) ---
    //
    let pageSize = 12;
    let currentPage = 1;

    function renderExhibitionPage() {
        worksList.innerHTML = "";

        const start = (currentPage - 1) * pageSize;
        const pageItems = filtered.slice(start, start + pageSize);

        pageItems.forEach(item => {
            worksList.insertAdjacentHTML("beforeend", `
                <li>
                    <a href="${getPage(item)}">
                        <img src="${getThumb(item)}">
                        <div><span>${getTitle(item)}<br>${item.medium || ""}</span></div>
                    </a>
                </li>
            `);
        });

        $('#works-list > li').each(function() { $(this).hoverdir(); });
        renderExhibitionPagination();
    }

    function renderExhibitionPagination() {
        const totalPages = Math.ceil(filtered.length / pageSize);
        const pagination = document.getElementById("pagination-works");
        pagination.innerHTML = "";

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            pagination.insertAdjacentHTML("beforeend", `
                <span class="${i === currentPage ? 'active' : ''}"
                      onclick="changeExhibitionPage(${i})">${i}</span>
            `);
        }
    }

    window.changeExhibitionPage = function(page) {
        currentPage = page;
        renderExhibitionPage();
    };

    renderExhibitionPage();
}
	
/* ---------------------------------------------------------
   Tag Filters
--------------------------------------------------------- */	
	function loadGenreMode(genre, filters) {
		const filtered = items.filter(i =>
			i.genre &&
			i.genre.toLowerCase() === genre.toLowerCase()
		);
		renderFilteredList(`Genre: ${genre}`, filtered, filters);
	}

	function loadMediumMode(medium, filters) {
		const filtered = items.filter(i =>
			i.medium &&
			i.medium.toLowerCase() === medium.toLowerCase()
		);
		renderFilteredList(`Medium: ${medium}`, filtered, filters);
	}

    function loadCollectionMode(collection, filters) {
        const filtered = items.filter(i =>
            i.collection &&
            i.collection.toLowerCase() === collection.toLowerCase()
        );
        renderFilteredList(`Collection: ${collection}`, filtered, filters);
    }

	function loadDateMode(date, filters) {
		const filtered = items.filter(i =>
			i.dateAdded === date
		);
		renderFilteredList(`Added: ${date}`, filtered, filters);
	}

	function loadDisabledMode(filters) {
		const filtered = items.filter(i => i.disabled);
		renderFilteredList(`Disabled Works`, filtered, filters);
	}

	
	function renderFilteredList(title, filtered, filters) {
    const worksList = document.getElementById("works-list");

    document.getElementById("page-title").innerText = title;
    document.getElementById("exhibition-header").style.display = "none";
    document.getElementById("exhibitions-heading").style.display = "none";
    document.getElementById("pagination-exhibitions").innerHTML = "";
    document.getElementById("exhibitions-list").innerHTML = "";

    document.getElementById("works-heading").style.display = "block";
    worksList.innerHTML = "";

    filtered = applyFilters(filtered, filters);

    let pageSize = 16;
    let currentPage = 1;

    function renderPage() {
        worksList.innerHTML = "";

        const start = (currentPage - 1) * pageSize;
        const pageItems = filtered.slice(start, start + pageSize);

        pageItems.forEach(item => {
            worksList.insertAdjacentHTML("beforeend", `
                <li>
                    <a href="${getPage(item)}">
                        <img src="${getThumb(item)}">
                        <div><span>${getTitle(item)}<br>${item.medium || ""}</span></div>
                    </a>
                </li>
            `);
        });

        $('#works-list > li').each(function() { $(this).hoverdir(); });
        renderPagination();
    }

    function renderPagination() {
        const totalPages = Math.ceil(filtered.length / pageSize);
        const pagination = document.getElementById("pagination-works");
        pagination.innerHTML = "";

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            pagination.insertAdjacentHTML("beforeend", `
                <span class="${i === currentPage ? 'active' : ''}"
                      onclick="changePage(${i})">${i}</span>
            `);
        }
    }

    window.changePage = function(page) {
        currentPage = page;
        renderPage();
    };

    renderPage();
}

/* ---------------------------------------------------------
   FILTER UI EVENTS
--------------------------------------------------------- */
document.getElementById("filter-medium").addEventListener("change", e => {
    currentFilters.medium = e.target.value || null;
    updateURLWithFilters();
    loadGallery();
});

document.getElementById("filter-genre").addEventListener("change", () => {
    currentFilters.genre = document.getElementById("filter-genre").value || null;
    updateURLWithFilters();
    loadGallery();
});


document.getElementById("filter-sold").addEventListener("change", e => {
    currentFilters.sold = e.target.value || null;
    updateURLWithFilters();
    loadGallery();
});

document.getElementById("filter-search").addEventListener("input", e => {
    currentFilters.search = e.target.value;
    updateURLWithFilters();
    loadGallery();
});

// Collapsible search control: default collapsed. Toggle shows/hides the search input.
function setSearchCollapsed(collapsed) {
    const input = document.getElementById('filter-search');
    const toggle = document.getElementById('filter-search-toggle');
    if (!input || !toggle) return;
    if (collapsed) {
        input.style.display = 'none';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = 'Show';
    } else {
        input.style.display = '';
        toggle.setAttribute('aria-expanded', 'true');
        toggle.textContent = 'Hide';
        input.focus();
    }
}

const toggleBtn = document.getElementById('filter-search-toggle');
if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        const input = document.getElementById('filter-search');
        if (!input) return;
        const collapsed = input.style.display === 'none' || input.style.display === '' && window.getComputedStyle(input).display === 'none';
        setSearchCollapsed(collapsed);
    });
    toggleBtn.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            toggleBtn.click();
        }
    });
}

// Ensure search input is visible if there's an active search filter
try {
    const initialSearch = currentFilters.search || '';
    if (initialSearch && initialSearch.trim() !== '') setSearchCollapsed(false);
    else setSearchCollapsed(true);
} catch (e) { /* ignore during early init */ }

document.getElementById("filter-prints").addEventListener("change", e => {
    currentFilters.prints = e.target.checked ? true : null;
    updateURLWithFilters();
    loadGallery();
});

document.getElementById("filter-reset").addEventListener("click", () => {
    currentFilters = { medium: null, sold: null, genre: null, collection: null, prints: null, search: "" };

    try {
        const fm = document.getElementById("filter-medium"); if (fm) fm.value = "";
        const fg = document.getElementById("filter-genre"); if (fg) fg.value = "";
        const fs = document.getElementById("filter-sold"); if (fs) fs.value = "";
        const fp = document.getElementById("filter-prints"); if (fp) fp.checked = false;
        const fq = document.getElementById("filter-search"); if (fq) fq.value = "";
    } catch (e) { /* ignore */ }

    // Clear query string and hash so legacy hash filters (e.g. #collection-...) are removed
    updateURLWithFilters();
    try { history.replaceState(null, '', window.location.pathname + (window.location.search || '')); } catch (e) {}
    try { window.location.hash = ''; } catch (e) {}

    loadGallery();
});

/* ---------------------------------------------------------
   INIT
--------------------------------------------------------- */
loadGallery();
window.addEventListener("hashchange", loadGallery);

