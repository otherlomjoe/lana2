
/* ---------------------------------------------------------
   GLOBAL STATE
--------------------------------------------------------- */
let items = [];
let exhibitions = [];
let currentFilters = {
    medium: null,
    sold: null,
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
async function loadData() {
    if (items.length && exhibitions.length) return;

    exhibitions = await fetch("/gallery/exhibitions.json").then(r => r.json());
    items = await fetch("/gallery/works.json").then(r => r.json());
}

/* ---------------------------------------------------------
   Populate Filters Dynamically
--------------------------------------------------------- */	
function populateMediumFilter() {
    const select = document.getElementById("filter-medium");

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
            (i.medium || "").toLowerCase().includes(q)
        );
    }

    return filtered;
}

/* ---------------------------------------------------------
   ROUTER
--------------------------------------------------------- */
async function loadGallery() {
    await loadData();
	populateMediumFilter();
	populateGenreFilter();

	const hash = window.location.hash.replace("#", "");

	if (hash.startsWith("tag-")) {
		loadTagMode(hash.replace("tag-", ""), currentFilters);

	} else if (hash.startsWith("genre-")) {
		loadGenreMode(hash.replace("genre-", ""), currentFilters);

	} else if (hash.startsWith("medium-")) {
		loadMediumMode(hash.replace("medium-", ""), currentFilters);

	} else if (hash.startsWith("date-")) {
		loadDateMode(hash.replace("date-", ""), currentFilters);

	} else if (hash.startsWith("disabled-")) {
		loadDisabledMode(currentFilters);

	} else if (hash) {
		loadExhibitionMode(hash, currentFilters);

	} else {
		loadGalleryMode(currentFilters);
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

	const exData = exhibitions.map(ex => {
		const base = ex.imageSlug.replace(/-/g, "");
		const thumb = ex.thumbnail || `/gallery/all/thumbs/${base}thumb.jpg`;

		return {
			link: ex.link || `gallery3.html#${ex.slug}`,
			thumb: thumb,
			text: `${ex.title || ex.name}<br>${ex.date || ""}`
		};
	});

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
        text: `${getTitle(item)}<br>${item.medium || ""}`
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
    document.getElementById("ex-meta").innerText = ex.date || "";

    // Location (optional)
    if (ex.location) {
        document.getElementById("ex-meta").innerText +=
            `  •  ${ex.location}`;
    }

    // Description
    document.getElementById("ex-description").innerText =
        ex.description || "";

    // Auto‑derive hero + thumbnail from imageSlug
    const base = ex.imageSlug.replace(/-/g, "");

    const hero = ex.hero || `/gallery/all/full/${base}.jpg`;
    const thumb = ex.thumbnail || `/gallery/all/thumbs/${base}thumb.jpg`;

    // Show hero image under date
    document.getElementById("ex-hero").innerHTML =
        `<img src="${hero}" alt="${title}" style="max-width:100%;margin:20px 0;">`;

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
    loadGallery();
});

document.getElementById("filter-genre").addEventListener("change", () => {
    filters.genre = document.getElementById("filter-genre").value;
    loadGalleryMode(filters);
});


document.getElementById("filter-sold").addEventListener("change", e => {
    currentFilters.sold = e.target.value || null;
    loadGallery();
});

document.getElementById("filter-search").addEventListener("input", e => {
    currentFilters.search = e.target.value;
    loadGallery();
});

document.getElementById("filter-reset").addEventListener("click", () => {
    currentFilters = { medium: null, sold: null, search: "" };

    document.getElementById("filter-medium").value = "";
    document.getElementById("filter-sold").value = "";
    document.getElementById("filter-search").value = "";

    loadGallery();
});

/* ---------------------------------------------------------
   INIT
--------------------------------------------------------- */
loadGallery();
window.addEventListener("hashchange", loadGallery);

