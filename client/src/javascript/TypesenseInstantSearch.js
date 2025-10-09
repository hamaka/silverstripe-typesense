import instantsearch from "instantsearch.js";
import TypesenseInstantSearchAdapter from "typesense-instantsearch-adapter";
import {infiniteHits, searchBox, stats} from 'instantsearch.js/es/widgets'

export default class TypesenseInstantSearch {
  constructor({indexName})
  {
    this.indexName = indexName;

    // Check if the current URL is a localhost URL and append ".local" to indexName if it is
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
      this.indexName = `${this.indexName}_local`;
    }

    // internal flags/state
    this._suppressHistory = false;
    this._debounceTimer = null;
    this._originalTitle = document.title;

    this.init();
  }

  init()
  {
    this.searchContainer = document.getElementsByClassName('js-global-search-container')[0];
    this.typesenseApiKey = this.searchContainer?.getAttribute('data-typesense-search-key');
    this.typesenseServer = this.searchContainer?.getAttribute('data-typesense-server');

    if ( this.typesenseApiKey && this.typesenseServer )
    {

      const typesenseInstantsearchAdapter = new TypesenseInstantSearchAdapter({
        server: {
          apiKey: this.typesenseApiKey,
          nodes: [
            {
              url: this.typesenseServer
            }
          ]
        },
        additionalSearchParameters: {
          query_by: 'Title,FullPage',
          highlight_affix_num_tokens: 20
        }
      });

      const searchClient = typesenseInstantsearchAdapter.searchClient;
      this.instantSearch = instantsearch({
        searchClient,
        indexName: this.indexName,
        searchFunction(helper)
        {
          if ( helper.state.query?.trim().length > 0 )
          {
            helper.search();
          }
        }
      });

      this.addWidgets();

      this.instantSearch.start();

      // Bind input listeners after widget has rendered
      this._attachSearchInputHandlers();

      // Apply initial query from URL (?q=...)
      this._applyInitialQueryFromURL();

      // Toggle gedrag
      this.openButtons = document.getElementsByClassName('js-search-open');
      this.closeButtons = document.getElementsByClassName('js-search-close');

      // Add event listeners to all open buttons
      for (let i = 0; i < this.openButtons.length; i++) {
        this.openButtons[i]?.addEventListener('click', () => {
          this._openOverlay();
        });
      }

      // Add event listeners to all close buttons
      for (let i = 0; i < this.closeButtons.length; i++) {
        this.closeButtons[i]?.addEventListener('click', () => {
          this._closeOverlayAndClear();
        });
      }

      // ESC sluit overlay
      document.addEventListener('keydown', (e) => {
        if ( e.key === 'Escape' && !this.searchContainer.classList.contains('d-none') )
        {
          // Use the first close button if available
          if (this.closeButtons.length > 0) {
            this.closeButtons[0].click();
          }
        }
      });

      // Handle back/forward navigation to sync query and overlay
      window.addEventListener('popstate', () => {
        const q = this._getQueryFromURL();
        this._suppressHistory = true;
        if (q) {
          this._setSearchInputValue(q);
          this.instantSearch.helper.setQuery(q).search();
          this._openOverlay(false);
        } else {
          // empty query: close overlay and clear results
          if (!this.searchContainer.classList.contains('d-none')) {
            this._closeOverlayAndClear(false);
          } else {
            // ensure helper query and input cleared even if already closed
            this._setSearchInputValue("");
            this.instantSearch.helper.setQuery("").search();
          }
        }
        // Update document title and track the virtual pageview for back/forward navigation
        this._updateDocumentTitle(q);
        const newUrl = window.location.pathname + window.location.search + window.location.hash;
        this._trackVirtualPageview(newUrl);
        this._suppressHistory = false;
      });
    }
  }

  addWidgets()
  {
    this.instantSearch.addWidgets([
      searchBox({
        container: '#global-search-input',
        showSubmit: false,
        showReset: false,
        placeholder: 'Vul een zoekterm in...',
        autofocus: true
      }),
      infiniteHits({
        container: '#global-hits',
        templates: {
          item(data, data2)
          {
            return `<div class="search-result typography">

                <h3><a href="${data.URL}">${data._highlightResult.Title?.value}</a></h3>
                <p style=\"overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;\">
                  ${data._snippetResult.FullPage?.value || ""}
                </p>
              </div>
            `
          },
          empty: 'Geen resultaten gevonden voor "<q>{{ query }}</q>."',
          showMoreText(data)
          {
            return `<button class="btn">Meer laden</button>`;
          }
        }
      }),
      stats({
        container: '#global-stats',
        templates: {
          text: ({nbHits, hasNoResults}) => hasNoResults ? '' : (nbHits === 1 ? `${nbHits.toLocaleString()} resultaat` : `${nbHits.toLocaleString()} resultaten`)
        }
      })
    ]);
  }

  reset()
  {
    this.instantSearch.dispose();
    this.addWidgets();
    this.instantSearch.start();
    this.instantSearch.helper.setQuery("").search();
    // After resetting, reattach handlers and clear URL param
    this._attachSearchInputHandlers();
    this._updateURLWithQuery("");
  }

  // --- Helpers for URL, history, and UI ---

  _attachSearchInputHandlers() {
    const input = document.querySelector('#global-search-input input[type="search"]');
    if (!input) return;

    // Remove existing listener by replacing it (no-op if none)
    input.removeEventListener?.('input', this._onInputHandler);

    this._onInputHandler = (e) => {
      const term = e.target.value || "";
      // Debounce history updates so we don't push for every keystroke
      clearTimeout(this._debounceTimer);
      this._debounceTimer = setTimeout(() => {
        if (this._suppressHistory) return;
        this._updateURLWithQuery(term);
      }, 500);
    };

    input.addEventListener('input', this._onInputHandler);
  }

  _setSearchInputValue(q) {
    const input = document.querySelector('#global-search-input input[type="search"]');
    if (!input) return;
    const current = input.value ?? "";
    if (current === q) return;
    const prev = this._suppressHistory;
    this._suppressHistory = true;
    input.value = q || "";
    // Manually dispatch an input event so InstantSearch picks up the value if needed
    const evt = new Event('input', { bubbles: true });
    input.dispatchEvent(evt);
    this._suppressHistory = prev;
  }

  _openOverlay(focus = true) {
    this.searchContainer.classList.remove('d-none');
    $('.search-box-wrapper').animate({scrollTop: 0}, "fast");
    if (focus) {
      this.searchContainer.querySelector('input[type="search"]')?.focus();
    }
  }

  _closeOverlayAndClear(updateHistory = true) {
    this.searchContainer.classList.add('d-none');
    this.reset();
    this._setSearchInputValue("");
    this._updateDocumentTitle("");
    if (updateHistory) {
      this._updateURLWithQuery("");
    }
  }

  _applyInitialQueryFromURL() {
    const q = this._getQueryFromURL();
    if (q) {
      this._suppressHistory = true;
      this._setSearchInputValue(q);
      this.instantSearch.helper.setQuery(q).search();
      this._updateDocumentTitle(q);
      this._openOverlay(false);
      this._suppressHistory = false;
    }
  }

  _getQueryFromURL() {
    try {
      const url = new URL(window.location.href);
      const q = url.searchParams.get('q') || '';
      return q;
    } catch (e) {
      return '';
    }
  }

  _updateURLWithQuery(q) {
    try {
      const url = new URL(window.location.href);
      if (q && q.trim().length > 0) {
        url.searchParams.set('q', q);
      } else {
        url.searchParams.delete('q');
      }
      // push new history entry so Back returns to previous query state
      const newUrl = url.pathname + (url.search ? url.search : '') + url.hash;
      window.history.pushState({q}, '', newUrl);
      // Update document title to reflect the current search term
      this._updateDocumentTitle(q);
      // Track virtual pageview for SPA-style navigation
      this._trackVirtualPageview(newUrl);
    } catch (e) {
      // no-op
    }
  }

  _updateDocumentTitle(q) {
    try {
      const term = (q || '').trim();
      if (term.length > 0) {
        document.title = `Zoekresultaten voor “${term}”`;
      } else {
        document.title = this._originalTitle || document.title;
      }
    } catch (e) {
      // ignore title errors
    }
  }

  // Send virtual pageview to Google Analytics (GA4/UA) and Matomo/Piwik if available
  _trackVirtualPageview(newUrl) {
    try {
      const fullUrl = window.location.origin + newUrl;
      const title = document.title || '';

      // GA4 via gtag
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'page_view', {
          page_location: fullUrl,
          page_path: newUrl,
          page_title: title
        });
      }

      // Universal Analytics
      if (typeof window.ga === 'function') {
        try { window.ga('set', 'page', newUrl); } catch (e) {}
        try { window.ga('set', 'title', title); } catch (e) {}
        try { window.ga('send', 'pageview'); } catch (e) {}
      }

      // Matomo / Piwik
      if (Array.isArray(window._paq)) {
        try { window._paq.push(['setCustomUrl', fullUrl]); } catch (e) {}
        try { window._paq.push(['setDocumentTitle', title]); } catch (e) {}
        try { window._paq.push(['trackPageView']); } catch (e) {}
      }
    } catch (e) {
      // swallow any analytics errors to avoid breaking UX
    }
  }
}
