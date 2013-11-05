(function($) {
  "use strict";

  $(document).ready(function() {
    // Moves the search form into the search result page.
    if (window.location.href.indexOf("search/") > -1) {
      var search = $('.search-field-wrapper');
      search.addClass('search-field-in-content');
      search.addClass('js-search-field-in-content');

      // Remove label.
      search.find('.label-search').remove();

      // Add the search field.
      search.insertAfter('.pane-search-result-count');

      // Ensure that the spinner and other stuff works by wrapping it in a
      // search div.
      search.wrap('<div class="search"></div>');

      // Remove tabs (panels visibility rules do not work!).
      $('.pane-page-tabs').remove();

      // Hide top menu bar link.
      $('.topbar-link-search').hide();
    }
  });
}(jQuery));
