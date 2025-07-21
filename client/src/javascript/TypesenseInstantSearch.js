import instantsearch from "instantsearch.js";
import TypesenseInstantSearchAdapter from "typesense-instantsearch-adapter";
import {infiniteHits, searchBox, stats} from 'instantsearch.js/es/widgets'

export default class TypesenseInstantSearch {
  constructor({indexName})
  {
    this.indexName = indexName;

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

      // Toggle gedrag
      this.openButton = document.getElementsByClassName('js-search-open')[0];
      this.closeButton = document.getElementsByClassName('js-search-close')[0];

      this.openButton?.addEventListener('click', () => {
        this.searchContainer.classList.remove('d-none');
        $('.search-box-wrapper').animate({scrollTop: 0}, "fast");
        this.searchContainer.querySelector('input[type="search"]')?.focus();
      });

      this.closeButton?.addEventListener('click', () => {
        this.searchContainer.classList.add('d-none');
        this.reset();
      });

      // ESC sluit overlay
      document.addEventListener('keydown', (e) => {
        if ( e.key === 'Escape' && !this.searchContainer.classList.contains('d-none') )
        {
          this.closeButton.click();
        }
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

                <h3><a href="${data.URL}" target="_blank">${data._highlightResult.Title?.value}</a></h3>
                <p style="overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
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
  }
}
