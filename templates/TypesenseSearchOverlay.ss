<% if $TypesenseSearchKey && $TypesenseServer %>
    <div class="search-overlay d-none js-global-search-container"
         data-typesense-search-key="$TypesenseSearchKey"
         data-typesense-server="$TypesenseServer">
        <div class="search-overlay__wrapper">
            <div class="container container--full-width">
                <div class="search-overlay__close js-search-close" aria-label="Sluiten">
                    <svg class="" width="43" height="43" viewBox="0 0 43 43" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="43" height="43" rx="6.37037" fill="#E83A47"/>
                        <path d="M15 15L27.7279 27.7279" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        <path d="M15 28L27.7279 15.2721" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
            <section class="section section--full-width section--filled">

                <div class="container search-overlay__container">
                    <div class="search-overlay__content">
                        <div id="global-search-input"></div>
                        <div id="global-stats" class="search-overlay__stats typography"></div>
                        <div id="global-hits" class="search-overlay__results"></div>
                    </div>
                </div>
            </section>
        </div>
    </div>
<% end_if %>
