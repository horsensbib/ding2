<?php

/**
 * @file
 * Implements searching functionality
 */

namespace Page;

use Behat\Mink\Session;
use SensioLabs\Behat\PageObjectExtension\PageObject\Factory;
use Stack;

/**
 * Class SearchPage
 *
 * @package Page
 */
class SearchPage extends PageBase {

  /**
   * Stack holding the number of result per search. Primarily used for testing facets.
   *
   * @var Stack $expectedResultsCount
   */
  private $expectedResultsCount;

  /**
   * If 0 it will be interpreted as 'all'-
   *
   * @var integer $maxPageTraversals
   */
  protected $maxPageTraversals = 0;

  /**
   * Contains all log messages from verbose mode.
   *
   * @var string $messageCollection
   */
  protected $messageCollection = '';

  /**
   * Path to search result pages.
   *
   * @var string $path
   */

  protected $path = '/search/ting/{string}';

  /**
   * Holds the latest search result we scraped from the pages.
   *
   * @var array $searchResults
   */
  protected $searchResults = array();


  /**
   * Flag for verbose settings.
   *
   * @var string $verboseSearchResults
   */
  protected $verboseSearchResults = 'off';

  /**
   * Version 4: Checks if the pagination elements are shown correctly on page.
   *
   * First page:   (1) 2 Næste
   * Second :      forrige 1 (2) 3 Næste
   * Third:        første forrige 1 2 (3) 4 Næste
   * Fourth:       første forrige 1 2 3 (4) 5 Næste
   * etc...
   * Last page:    første forrige (...) 9 10 11 12 13 14 15 16 (17)   (so næste is not shown)
   *               første forrige (...) 4 5 6 7 8 9 10 (11) 12 næste
   * so always show the next page + Næste, and
   *           all previously shown pages (up to 7 previous, which means ellipsis is shown from ?page=8
   *
   * returns a text string containing any deviances found. It doesn't fail on its own. If the returned
   * string is empty, there was no problems found.
   */
  public function checkPaginationElements() {
    // Check if the pagination elements are shown.
    $pg = $this->findAll('css', $this->elements['pager-elements']);
    if (!$pg) {
      return "Pagination elements are not shown.";
    }

    // Find out which is the current page. This goes into our checks.
    $curpg = $this->getCurrentPage();

    // These vars collect our check results.
    // Pagination element counter.
    $xte = 0;
    $pgFirst = -1;
    $pgForrige = -1;
    $pgNaeste = -1;
    $pgEllipse = 0;
    $pgLast = 0;
    foreach ($pg as $pElement) {
      $pElementText = $pElement->getAttribute('class');
      $xte++;
      switch ($pElementText) {
        case "pager-first first":
          if (null !== $pElement->find('css', 'a')) {
            $pgFirst = $xte;
          }
          break;

        case "pager-previous":
        case "pager-previous first":
          if (null !== ($pElement->find('css', 'a'))) {
            $pgForrige = $xte;
          }
          break;

        case "pager-next last":
          if (null !== $pElement->find('css', 'a')) {
            $pgNaeste = $xte;
          }
          break;

        case "pager-ellipsis":
          // In version 4 we can have up to two ellipses.
          $pgEllipse++;
          break;

        case "pager-current":
          // We must not have a link on this page.
          $this->logMsg((null !== $pElement->find('css', 'a')), "Pagination has link to current page");
          break;

        default:
          // Count the number of links we can go to.
          if (null !== $pElement->find('css', 'a')) {
            $pgLast++;
          }
      }
    }
    // Now do the checks of placements.
    switch ($curpg) {
      case 1:
        // We don't want to see first, forrige.
        $this->logMsg(($pgFirst != -1), "Pagination: 'første' is shown on page 1.");
        $this->logMsg(($pgForrige != -1), "Pagination: 'forrige' is shown on page 1");
        // We don't want to see more than 2 indexes if we are on page 1.
        $this->logMsg(($pgEllipse > 1), "Pagination: Elipsis was shown on page 1");
        $this->logMsg(($pgLast > 2), "Pagination: on page 1 we only expect link to next page");
        // $this->logMsg(($curpg>=$pgLast and $pgNaeste != -1), "Pagination: 'Næste' is shown on last page (" . $curpg . ")");
        // $this->logMsg(($curpg<$pgLast and $pgNaeste == -1), "Pagination: 'Næste' is not shown on page " . $curpg);
        break;

      case 2:
        // We don't want to see first yet, but we want to see forrige.
        $this->logMsg(($pgFirst != -1), "Pagination: 'Første' is shown on page 2");
        $this->logMsg(($pgForrige == -1), "Pagination: 'Forrige' is not shown on page 2");
        $this->logMsg(($pgEllipse > 1), "Pagination: Elipsis not expected more than once on page 2");
        $this->logMsg(($pgLast != 2), "Pagination: on page 2 we can go to more than page 1 and 3. Unexpected.");
        // $this->logMsg(($curpg>=$pgLast and $pgNaeste != -1), "Pagination: 'Næste' is show on last page (" . $curpg . ")");
        // $this->logMsg(($curpg<$pgLast and $pgNaeste == -1), "Pagination: 'Næste' is not shown on page " . $curpg);

        break;

      default:
        // This goes for the remaining pages.
        $this->logMsg(($pgFirst == -1), "Pagination: 'Første' is not shown on page " . $curpg);
        $this->logMsg(($pgForrige == -1), "Pagination: 'Forrige' is not shown on page " . $curpg);
        $this->logMsg(($pgLast >= ($curpg + 1)), "Pagination: on page " . $curpg . " we can go to more than page " . $pgLast . " directly");
        $this->logMsg(($pgEllipse > 2), "Pagination: Ellipsis should not be shown more than once on page " . $curpg);
        $this->logMsg(($pgEllipse == 0), "Pagination: Ellipsis should at least be shown once on page " . $curpg);
        // $this->logMsg(($curpg>$pgLast and $pgNaeste != -1), "Pagination: 'Næste' is shown on last page (" . $curpg . ")");
        // $this->logMsg(($curpg<=$pgLast and $pgNaeste == -1), "Pagination: 'Næste' is not shown on page " . $curpg);
        break;

    }
  }

  /**
   * CheckPaginationOnAllPages.
   *
   * @return string
   *    Empty if all is okay.
   *
   * @throws Exception
   *    In case of error.
   */
  public function checkPaginationOnAllPages() {
    // First we check if we have a known search result in memory.
    $lastsearch = count($this->searchResults);
    if (!$lastsearch) {
      return "No search result is present in memory. Use 'Then pageing allows to get all the results' first.";
    }

    $lastpage = $this->searchResults[$lastsearch - 1]->page;

    // Check all pages now.
    for ($i = 0; $i < ($lastpage - 1); $i++) {
      // Move to page using the pagination link.
      $result = $this->goToPage(($i + 1));
      if ($result != "") {
        return "Could not go to page " . $i + 1 . ": " . $result;
      }
      // Check that the pagination elements are shown correctly for this page.
      $this->logMsg(true, $this->checkPaginationElements());

      // Now check that the page contains the titles we expect, as we collected earlier (and if we didn't this will fail).
      // Notice that the collection of search result uses the direct URL and not the pagination, which we check in this function.
      $lRes = $this->getPageFullOfSearchResults(($i + 1));

      $this->LogMsg(true, $this->checkSearchResultPageAgainstKnownContent($lRes));
    }
    return "";
  }

  /**
   * CheckPosts
   *
   * @param string $attribute
   *    The kind of material to check, like forfatterbeskrivelse, coverpage etc.
   * @param string $mode
   *    All means every post needs to have it. Otherwise, just one post needs to check out.
   *
   * @return string
   *    Nonempty if failure.
   */
  public function checkPostsWithXXInTheSearchResult($attribute, $mode) {
    $lastsearch = count($this->searchResults);

    // Just one is okay.
    $txt_accessibility = false;
    $txt_cover = false;
    $txt_materiale = false;
    $txt_isSamling = false;
    $txt_serie = false;
    $txt_forfatterbeskrivelse = false;
    $okay = false;

    // We do the reverse if we expect all posts to have the attribute.
    if ($mode == "all") {
      $txt_accessibility = true;
      $txt_cover = true;
      $txt_materiale = true;
      $txt_isSamling = true;
      $txt_serie = true;
      $txt_forfatterbeskrivelse = true;
      $okay = true;
    }

    if (!$lastsearch or $lastsearch == 0)
    {
      return "Search result is not found.";
    }
    for ($i = 0; $i < $lastsearch - 1; $i++) {
      if ($mode == "all") {
        // Set to false, and keep it false, if any one is not found in the entire result.
        $txt_accessibility = (strlen($this->searchResults[$i]->access) == 0) ? false : $txt_accessibility;
        $txt_cover = (strlen($this->searchResults[$i]->cover) == 0) ? false : $txt_cover;
        $txt_isSamling = (!$this->searchResults[$i]->collection) ? false : $txt_isSamling;
        $txt_materiale = (strlen($this->searchResults[$i]->link) == 0) ? false : $txt_materiale;
        $txt_serie = (strlen($this->searchResults[$i]->serie) == 0) ? false : $txt_serie;
      }
      else {
        // Set to true, and keep it as true in case at least one exists.
        $txt_accessibility = ($this->searchResults[$i]->access != "") ? true : $txt_accessibility;
        $txt_cover = (strcmp($this->searchResults[$i]->cover, '')) ? true : $txt_cover;
        $txt_isSamling = ($this->searchResults[$i]->collection) ? true : $txt_isSamling;
        $txt_materiale = ($this->searchResults[$i]->link != "") ? true : $txt_materiale;
        $txt_serie = ($this->searchResults[$i]->serie != "") ? true : $txt_serie;
      }
    }

    switch (strtolower($attribute)) {
      case 'tilgængelig':
      case 'tilgængelighed':
        $okay = $txt_accessibility;
        break;

      case 'forside':
      case 'cover':
        $okay = $txt_cover;
        break;

      case 'materialesamling':
        $okay = $txt_isSamling;
        break;

      case 'forfatterbeskrivelse':
        $okay = $txt_forfatterbeskrivelse;
        break;

      case 'materialetype':
        $okay = $txt_materiale;
        break;

      case 'serie':
        $okay = $txt_serie;
        break;

    }

    if (!$okay) {
      if ($mode == "all") {
        return "Not all posts have " . $attribute . " in the search result.";
      }
      else {
        return "Found none with " . $attribute . " in the search result.";
      }
    }
    return "";
  }


  /**
   * Collects all search results shown on the current page and compares to the expected results given as parameter.
   *
   * @param array $sRes
   *    The last known search result, but notice, only the ones on the given page.
   *
   * @return string
   *    Containing any found deviances. Empty if a-okay.
   *
   * @throws Exception
   *    If we haven't done a search before this is invoked.
   */
  public function checkSearchResultPageAgainstKnownContent($sRes = array()) {
    // Find all the titles in the search result.
    $founds = $this->findAll('css', '.search-results .ting-object h2 a');
    if (!$founds) {
      return "Couldn't find search result.";
    }

    // Count of items on page from top (works as placement on page as well).
    $xte = 0;
    $txt = "";
    foreach ($founds as $srItem) {
      // Fail if we reach the end of the expected results.
      if ($xte == count($sRes)) {
        $txt = $txt . "Found more on page than expected. Expected: " . $xte . ". \n";
      }

      // Compare, unless we're above the array index.
      if ($xte < count($sRes)) {
        if ($srItem->getText() != $sRes[$xte]->title) {
          $txt = $txt . "Title #" . $xte . " not found. (Expected/Actual)=(" . $sRes[$xte]->title . "/" . $srItem->getText() . ")\n";
        }
      }
      $xte++;
    }
    if ($xte < count($sRes)) {
      $txt = $txt . "Expected more titles on page. (Actual " . ($xte - 1) . ", expected " . count($sRes) . ")\n";
    }
    return $txt;
  }

  /**
   * CheckSorting.
   *
   * @param string $sortOption
   *    One of title_ascending, title_descending, creator_ascending/descending, date_ascending/descending.
   *
   * @return string
   *    Nonempty if error.
   */
  public function checkSorting($sortOption) {
    // Check we're looking at a search result page.
    $page = $this->find('css', 'div.search-results li.list-item');
    if (null === $page) {
      return "Attempting check of sorting when not on a search result page with results found.";
    }
    // So we're basically traversing the search result pages, and constantly check against the previous
    // title shown, and compare if the relation between the two is fulfilled by the sort-criteria.
    $this->getEntireSearchResult();

    // Track if we've got any errors so we can flag it.
    $sortingOK = true;

    if (count($this->searchResults) < 2) {
      return "Attempting check of sorting but got less than two results.";
    }

    for ($i = 1; $i < count($this->searchResults) - 1; $i++) {
      $isOK = false;
      switch ($sortOption) {
        case 'title_ascending':
          $isOK = (strcasecmp($this->searchResults[$i - 1]->title, $this->searchResults[$i]->title)<=0) ? true : false;
          break;

        case 'title_descending':
          $isOK = (strcasecmp($this->searchResults[$i - 1]->title, $this->searchResults[$i]->title)>=0) ? true : false;
          break;

        case 'creator_ascending':
          $isOK = (strcasecmp($this->searchResults[$i - 1]->creator, $this->searchResults[$i]->creator)<=0) ? true : false;
          break;

        case 'creator_descending':
          $isOK = (strcasecmp($this->searchResults[$i - 1]->creator, $this->searchResults[$i]->creator)>=0) ? true : false;
          break;

        case 'date_ascending':
          $isOK = (strcasecmp($this->searchResults[$i - 1]->published, $this->searchResults[$i]->published)<=0) ? true : false;
          break;

        case 'date_descending':
          $isOK = (strcasecmp($this->searchResults[$i - 1]->published, $this->searchResults[$i]->published)>=0) ? true : false;
          break;

        default:
          return "Automation Error: checking sorting with unhandled, but valid sortOption: " . $sortOption;
      }
      if ($isOK === false) {
        $this->logMsg(true, "Sorting on (" . $sortOption . ") is not ok:            (page " . $this->searchResults[$i]->page
              . " #" . $this->searchResults[$i]->item . ")");
        $this->logMsg(true, "    " . $this->searchResults[$i - 1]->title . " by " . $this->searchResults[$i - 1]->creator . " ("
              . $this->searchResults[$i - 1]->published . ")");

        $this->logMsg(true, "  is listed before");
        $this->logMsg(true, "    " . $this->searchResults[$i]->title . " by " . $this->searchResults[$i]->creator . " ("
              . $this->searchResults[$i-1]->published . ")");
        $sortingOK = false;
      }
    }
    if ($sortingOK === false) {
      return "Sorting not as expected.";
    }
  }

  /**
   * CompareResultSizeWithExpected.
   *
   * @return string
   *    empty if we found the number of results as we expected.
   */
  public function compareResultSizeWithExpected() {
    if ($this->getExpectedSearchResultSize() != 0) {
      // See if we can find the number of results from the page.
      $expectCount = $this->returnShownSizeOfSearchResult();
      if ($this->getExpectedSearchResultSize() != $expectCount) {
        return "Did not find the expected amount of posts. (Found: " . $expectCount . ". Expected:" . $this->getExpectedSearchResultSize();
      }
    }
    else {
      // Do nothing, but put it in the log.
      $this->logMsg(true, "An expected number of items were not set.");
    }
    return "";
  }


  /**
   * This searches for a pattern anywhere in the search results.
   *
   * @params string $regexp
   *    Contains the regular expression to search for.
   */
  public function findRegEx($regexp) {

    // Run through the entire search result to see if the title is there.
    $found = false;
    $xte = 0;
    for ($i = 0; $i < count($this->searchResults); $i++) {
      if (preg_match('/' . $regexp . '/', $this->searchResults[$i]->title)) {
        $found = true;
        $this->logMsg(($this->verboseSearchResults == 'on'),
              "Found " . $regexp . " on page " . $this->searchResults[$i]->page .
              " as item# " . $this->searchResults[$i]->item . "\n");
      }
      $xte++;
    }
    return $found;
  }

  /**
   * FindTitleOnPage.
   *
   * @param string $title
   *    The title to search for.
   *
   * @return string
   *    NonEmpty if not found.
   */
  public function findTitleOnPage($title) {
    $founds = $this->findAll('css', '.search-results li.list-item');
    if (!$founds) {
      return "Could not find a search result.";
    }
    $lb_found = false;
    $xte = 0;
    foreach ($founds as $srItem) {
      $srItemTitle = $srItem->find('css', 'h2 a');
      if ($srItemTitle) {
        if ($srItemTitle->getText() == $title) {
          $lb_found = true;
          $this->logMsg(($this->verboseSearchResults == 'on'), "Fandt '" . $title . "' som nummer " . $xte . " på siden.");
        }
      }
      $xte++;
    }
    if ($lb_found === false) {
      return "Did not find " . $title . " on page";
    }
    return "";
  }

  /**
   * GetActualSearchResultSize.
   *
   * Contrast this to getShownSizeOfSearchResult.
   * Notice this is possibly limited by the verbose/control setting of maxSearchPages.
   *
   * @return int
   *    Is the number of search results we actually found by scraping them off the pages.
   */
  public function getActualSearchResultSize() {
    return count($this->searchResults);
  }

  /**
   * GetCurrentPage.
   *
   * @return string
   *    The page number of the current search result page.
   *
   *    Notice, it fails if the pagination element for current page is not present on the page.
   */
  public function getCurrentPage() {
    // Fail if there's not a current-page element on the pagination. We don't actually use the $curpg for anything else.
    $curpg = $this->getElement("pager-current");

    // Now pick out the page number from the URL, bearing in mind it may be nonexistant, in which case we are on page 1.
    $url = $this->getSession()->getCurrentUrl();
    if ($url != "") {
      $urlElements = parse_url($url);
      if (array_key_exists('query', $urlElements)) {
        // We have parameters in link url.
        parse_str($urlElements['query'], $urlParams);
        if (!array_key_exists('page', $urlParams)) {
          // We didn't get a page parameter in link url.
          return 1;
        }
        else {
          // We got a page parameter. Check it's value against the page number we are looking for.
          return $urlParams['page'] + 1;
        }
      }
      else {
        // No parameters at all would indicate the first page. That's okay, if that is what we were looking for.
        return 1;
      }
    }
  }


  /**
   * Building the entire search result into an array
   *
   * @return string
   *    Empty means everything was fine. Not empty means failed. Text will be the reason it failed.
   */
  public function getEntireSearchResult() {
    // Initialise.
    $this->searchResults = array();
    $founds = $this->findAll('css', '.search-results li.list-item');

    if (!$founds) {
      return "Didn't find a search result.";
    }

    // Now we need to await that all the availability data are collected. We will wait here until we cannot find
    // "Henter beholdningsoplysninger" anymore.
    $this->waitUntilTextIsGone(100, 'Henter beholdningsoplysninger');

    // Count of page number.
    $cnt = 1;
    $lb_continue = true;
    // Loop through the pages until we're on the last page.
    while ($lb_continue) {
      // Count of placement on page from top.
      $xte = 1;
      foreach ($founds as $srItem) {
        $this->scrollTo($srItem);
        $isSamling = false;

        // Get hold of the title (version 4).
        $titlelink = $srItem->find('css', '.ting-object h2 a');

        // Find the author and published date.
        $creator = $srItem->find('css', '.ting-object .field-name-ting-author');
        $txt_creator = "";
        $txt_published = "";
        if ($creator) {
          $txt_creator_full = $creator->getText();
          if (strlen($txt_creator_full) > 0) {
            $arrCreator = explode("(", $txt_creator_full);
            // So authors may be listed with their birth year in ( ), so we need the last item for date and first for author.
            if (count($arrCreator) >= 2) {
              $txt_creator = $arrCreator[0];
              $txt_published = substr($arrCreator[count($arrCreator) - 1], 0, 4);
            }
            else {
              $this->logMsg(true, "Creator was not followed with a '(' : " . $txt_title . " af " . $txt_creator_full);
            }
          }

        }

        // Find the series - there can be multiple.
        $series = $srItem->findAll('css', '.field-name-ting-series .field-item');
        $txt_serie = "";

        foreach ($series as $serie) {
          $txt_serie = $txt_serie . $serie->getText() . "\n";
        }

        // Find "tilgængelig som".
        $accessibilities = $srItem->findAll('css', '.availability p a');
        $txt_access = "";

        foreach ($accessibilities as $access) {
          $txt_access = $txt_access . $access->getText() . "\n";
        }

        /*
         * Now we'll check if we've got a collection. The way to be sure on the search page is
         * to check if there are different object types (bog, lydbog, dvd,...) listed.
         * We'll check if there ar more types found, and if the first one found is different
         * from any of the subsequent ones we will conclude that we have a collection.
         * If a collection consists only of one type, say 'bog', then we'll not be able
         * to spot it from the search-page. Only by opening the object and show it can we
         * see if the url contains "Collection" - this is not part of the current check.
         * Note: on the search screen all urls contain 'collection', so don't fall into that trap.
         */
        if ($txt_access != "") {
          $arr_samling = preg_split("/\n/", $txt_access);

          if (count($arr_samling) > 1) {
            $txt_mtype1 = $arr_samling[0];
            for ($i = 1; $i < count($arr_samling); $i++) {
              $isSamling = ($txt_mtype1!=$arr_samling[$i] and $arr_samling[$i] != '') ? true : $isSamling;
            }
          }
        }

        // Get the link that is shown as 'tilgængelig'. This needs to be present.
        $link = $srItem->find('css', '.availability a');
        $txt_link = "";
        if ($link) {
          $txt_link = $link->getText();
        }

        // And finally grab out the cover image, if present.
        $coverimg = $srItem->find('xpath', '//div[contains(@class,"ting-cover")]/img');
        $txt_cover = "";
        if ($coverimg) {
          $txt_cover = $coverimg->getAttribute('src');
        }

        $this->searchResults[] = (object) array(
          'page' => $cnt,
          'item' => $xte,
          'title' => $txt_title,
          'link' => $txt_link,
          'cover' => $txt_cover,
          'serie' => $txt_serie,
          'access' => $txt_access,
          'collection' => ($isSamling) ? 'yes' : 'no',
          'creator' => $txt_creator,
          'published' => $txt_published,
        );

        $ll = count($this->searchResults) - 1;
        $this->logMsg(($this->verboseSearchResults == 'on'), "Title: " . $this->searchResults[$ll]->title .
              ", by " . $this->searchResults[$ll]->creator . " (" . $this->searchResults[$ll]->published . ") "
                . " (page " . $this->searchResults[$ll]->page
                . " # " . $this->searchResults[$ll]->item . ")");

        // Next item on page.
        $xte = $xte + 1;
      }
      $this->logMsg(($this->verboseSearchResults == 'on'), "Total items listed on page: " . ($xte - 1));

      // Ready for next page:
      $cnt = $cnt + 1;
      $pageing = $this->find('css', '.pager .pager-next a');

      if (!$pageing) {
        // We trust this means we are at the end of the search result and we have scooped everything up.
        $lb_continue = false;
      }
      else {
        /*
         * This is a bit precarious, as we need to check if the cookie and 'ask librarians' overlays are
         * popping up. If so, we'll whack them down again, because they can disturb our clicking.
         * Scroll down and click 'næste'.
         */
        $this->scrollTo($pageing);

        try {
          $pageing->click();
        }
        catch (UnsupportedDriverActionException $e) {
          // Ignore.
        }
        catch (\Exception $e) {
          // Just try again... might save us.
          $this->scrollABit(500);
          $pageing->click();
        }

        $this->waitForPage();
        // Rescan the search results on this page.
        $founds = $this->findAll('css', '.search-results li.list-item');
      }
      if ($this->maxPageTraversals == ($cnt - 1)) {
        // Stop early because of setting in verbose/control.
        $lb_continue = false;
        $this->logMsg(($this->verboseSearchResults == "on"), "Stops after " . $cnt . " pages due to verbose setting.\n");
      }
    }
    $this->logMsg(($this->verboseSearchResults == "on"), "Total pages: " . ($cnt - 1) . "\nTotal items:" . count($this->searchResults) . "\n");
    return "";
  }

  /**
   * GetExpectedSearchResultSize - returns the last expected result.
   *
   * @params bool $pop
   *    Optional default false. If true, the last result will be popped from the stack too.
   *
   * @return int
   *    Nonempty if failure.
   */
  public function getExpectedSearchResultSize($pop = false) {
    // It's a bit crude, but I'm not sure where else to initialize this Stack variable.
    if (!$this->expectedResultsCount) {
      $this->expectedResultsCount = new Stack();
    }
    return $this->expectedResultsCount->get($pop);
  }


  /**
   * GetLargetsFacetAndClickit.
   *
   * @param array $facets
   *    List of elements containing facets.
   * @param bool $select
   *    Default true - if true sets expected result. If false, pops the last value.
   *
   * @return string
   *    Nonempty if failure.
   *
   * @throws Exception
   *    In case of error.
   */
  private function getLargestFacetAndClickIt($facets = array(), $select = true) {
    // Now we traverse them to find out how many results they 'promise' if selected.
    $largestName = "";
    $largestCount = 0;
    $largestCheckbox = $facets[0];
    foreach ($facets as $chkbox) {
      // This can be a getElement.
      $lcntElement = $chkbox->find('css', 'span.count');
      if ($lcntElement) {
        $lcnt = $lcntElement->getText();
      }
      else {
        return "Did not find correct structure of facet. Should contain a 'span class=count' element.";
      }
      // Remove the paranthesises to retrieve the number and check if this is the largest number
      // found this far. If so, we will save it for later use.
      $lcnt = substr($lcnt, 1, strlen($lcnt) - 2);
      if ($lcnt > $largestCount) {
        $largestCount = $lcnt;
        $largestName = $chkbox->find('css', 'a')->getText();
        $largestCheckbox = $chkbox;
      }
    }
    if (!$largestCheckbox) {
      return "Did not find a largest facet to choose.";
    }

    // Save the 'promise' to enable later checks and tell the tester what we attempt, so
    // if this fails there is a chance to interpretate the screenshot.
    if ($select) {
      $this->setExpectedSearchResultSize($largestCount);
    }
    else {
      if ($this->expectedResultsCount->pop() < 0) {
        return "Something went wrong with expected results - are you trying to deselect with selecting facets first?";
      };
    }
    $this->logMsg(true, "Clicks facet: " . $largestName . " to get " . $this->expectedResultsCount->get() . " results.");

    // Now we will select the largest one we ended up with.
    $link = $largestCheckbox->find('css', 'a');
    if ($link) {
      $this->scrollTo($link);
      $link->click();
      $this->waitForPage();
    }
    else {
      return "Expected to find a link in the html structure under a facet.";
    }
    return "";
  }

  /**
   * Get Current Max Page Traversel setting.
   *
   * @return int
   *    Set the maximum number of pages we want to traverse to scrape off the search result.
   */
  public function getMaxPageTraversals() {
    return $this->maxPageTraversals;
  }

  /**
   * Returns and resets the log messages. They can only be read once.
   *
   * @return string
   *    The logmessages.
   */
  public function getMessages() {
    $msg = $this->messageCollection;
    $this->messageCollection = "";
    return $msg;
  }

  /**
   * This returns an array containing the search results we expect to show on a particular page.
   *
   * @param int $pageNum
   *    The actual page we want to look at and extract.
   *
   * @return array
   *    Search results - the subset from $sRes which is contained on the wanted page.
   */
  public function getPageFullOfSearchResults($pageNum) {
    // Set up the array.
    $lRes = array();
    // Run through the entire search result we have stored.
    for ($i = 0; $i < count($this->searchResults); $i++) {
      // Add search results belong to the requested page to the array we return.
      if ($this->searchResults[$i]->page == $pageNum) {
        $lRes[] = $this->searchResults[$i];
      }
    }
    return $lRes;
  }


  /**
   * GetOpenScanSuggestions.
   *
   * @return string
   *    Empty if ok, otherwise the error message.
   */
  public function getOpenScanSuggestions() {
    // We need to enable a wait because we cannot control the timing.
    // You'd think we want to use the waitFor method, but it doesn't actually do this trick.
    // We need to enable a wait because we cannot control the timing.
    // It is possibly some dynamic javascript on the page that tricks it.
    $max = 300;
    $found = $this->find('css', $this->elements['autocomplete']);
    while (--$max > 0 && !$found) {
      usleep(100);
      $found = $this->find('css', $this->elements['autocomplete']);
    }

    // Report error if we ran out of time.
    if (!$found) {
      return "Openscan did not show any suggestions. ";
    }

    $found = $this->getElement('autocomplete');

    // It also takes a bit for the page to get the dynamics of the suggestions done. So we wait again.
    $max = 300;
    $cnt = 0;
    while (--$max > 0 && !$found->findAll("css", $this->elements['autocomplete-list'])) {
      usleep(100);
      // Refresh the search.
      $found = $this->getElement('autocomplete');
    }

    // Now we list the suggestions given.
    foreach ($found->findAll("css", $this->elements['autocomplete-list']) as $suggestion) {
      $this->logMsg(true, $suggestion->getText() . "\n");
      $cnt++;
    }
    if ($cnt == 0) {
      return "No suggestions were found.";
    }
    // All we can do is list the number for convenience. It's in the configuration how many there should be.
    $this->logMsg(true, "In total " . $cnt . " suggestions were shown. Check configurationen.");
    return "";
  }

  /**
   * GetRandomSearchResultToShowPost.
   *
   * @param string $criteria
   *    Can be coverpage to select only post with cover page, or any other value to not care about that.
   *
   * @return string
   *    Nonempty in case of failure.
   *
   * @throws \Exception
   *    In case of error.
   */
  public function getRandomSearchResultToShowPost($criteria) {
    // Check we're looking at a search result page.
    $pageRes = $this->findAll('css', 'div.search-results li.list-item');
    if (!$pageRes) {
      return "Is not on a search result page with any results";
    }
    // Choose a random title.
    $i = random_int(0, count($pageRes) - 1);

    if ($criteria == "coverpage") {
      // Attempt max 50 times to find a random post with cover page on it.
      $max = 50;
      while (--$max > 0 && !$pageRes[$i]->find('css', '.ting-object .ting-cover img')) {
        usleep(100);
        $i = random_int(0, count($pageRes) - 1);
      }
      if (!$pageRes[$i]->find('css', '.ting-object .ting-cover img')) {
        return "Could not find a result with a cover page.";
      }
    }

    $linkObj = $pageRes[$i]->find('css', '.ting-object h2 a');
    if (!$linkObj) {
      return "Did not find a link to the item " . $i . " on the page.";
    }
    // Pick out the last part of the URL.
    $linkArr = explode('/', $linkObj->getAttribute('href'));
    if (count($linkArr) == 0) {
      return "The link to the page is not wellformed: href=" . $linkObj->getAttribute('href');
    }
    $link = urlencode('ting/object/' . $linkArr[count($linkArr) - 1]);
    //$this->open(['string' => $link]);
    //$this->gotoPage($link);
    $this->getSession()->visit($link);
    $this->waitForPage();
  }

  /**
   * Tries to find the number of search results shown as total found posts on the search page.
   *
   * @return int
   *    The number of search results.
   */
  public function getShownSizeOfSearchResult() {
    // todo: use getElement here.
    $found = $this->find('css', '.pane-content .count');
    if (!$found) {
      $this->logMsg(true, "Couldn't find count of results on page.");
      return -1;
    }

    // GetText() will here be of the form "(nnn resultater)", where nnn is what we want.
    // So we will split the string on the space, then substr the '(' away.
    $resArr = explode(' ', $found->getText());
    if (count($resArr) == 0) {
      return "Couldn't interpret result as '(x resultater)'. All I found was this: " . $found->getText();
    }
    $expectCount = substr($resArr[0], 1);

    return $expectCount;
  }


  /**
   * Returns the verbose setting for search result
   *
   * @return string
   *   NonEmpty if failure.
   */
  public function getVerboseSearchResult() {
    return $this->verboseSearchResults;
  }

  /**
   * GoToPage.
   *
   * @param int $toPage
   *    Pagenumber to go to.
   *
   * @return string
   *    Nonempty in case of failure.
   *
   * @throws Exception
   *    In case of error.
   */
  public function goToPage($toPage) {
    // This is counter intuitive, but page 2 will have parameter "page=1", so we start by subtracting 1.
    $toPage = $toPage - 1;
    $paginations = $this->findAll('xpath', $this->elements['pager-links']);
    // Initialise link - the index into the array.
    $link = -2;

    for ($i = 0; $i < count($paginations); $i++) {
      // Pick out the pagination link.
      $url = $paginations[$i]->getAttribute('href');

      if (!$url) {
        return "Could not find a correct link in pagination elements to pages.";
      }

      // Now check if we can get the page parameter from the link.
      // It's okay if no parameters at all --> page 1. Similar if parameters are given, but 'page' is not --> page 1.
      $urlElements = parse_url($url);

      if (array_key_exists('query', $urlElements)) {
        // We have parameters in link url.
        parse_str($urlElements['query'], $urlParams);
        if (!array_key_exists('page', $urlParams)) {
          // We didn't get a page parameter in link url.
          if ($toPage == 0) {
            // So we are home free if we were looking for first page.
            $link = $i;
          }
        }
        else {
          // We got a page parameter. Check it's value against the page number we are looking for.
          if ($toPage == $urlParams['page']) {
            $link = $i;
          }
        }
      }
      else {
        // No parameters at all would indicate the first page. That's okay, if that is what we were looking for.
        if ($toPage == 0) {
          $link = $i;
        }
      }
    }
    if ($link < 0) {
      return "Go to page error. Could not find the requested page " . $toPage . " in pagination.";
    }
    // Now let's go to that page.
    $this->scrollTo($paginations[$link]);
    $paginations[$link]->click();
    $this->waitForPage();
  }

  /**
   * Log_msg - prints message on log if condition is true.
   *
   * @param bool $ifTrue
   *    Indicates if the message is to be printed.
   * @param string $msg
   *    Message to print.
   */
  public function log2Msg($ifTrue, $msg) {
    if ($ifTrue) {
      $this->messageCollection = $this->messageCollection . $msg;
    }
  }

  /**
   * PopExpectedSearchResultSize - pops and returns the last expected result.
   *
   * @return int
   *    The expected number of postings found.
   */
  public function popExpectedSearchResultSize() {
    // It's a bit crude, but I don't know where else to initialise this Stack.
    if (!$this->expectedResultsCount) {
      $this->expectedResultsCount = new Stack();
    }
    return $this->expectedResultsCount->pop();
  }

  /**
   * SetExpectedSearchResultSize - pops and returns the last expected result.
   */
  public function setExpectedSearchResultSize($size) {
    // It's a bit crude, but I don't know where else to initialise this Stack.
    if (!$this->expectedResultsCount) {
      $this->expectedResultsCount = new Stack();
    }
    /*
     * We fail diligently by just setting a -1 as expected result. That will never compare
     * to anything so we will get the actual failing condition at a later step.
     * Meanwhile we loaded the reason into the logmessages which should be revealed at that
     * time.
     */
    if (!is_numeric($size)) {
      $this->logMsg(true, "Tried to set expected result to a non-number: " . $size);
      $size = -1;
    }
    $this->expectedResultsCount->set($size);
  }

  /**
   * Searches for $string.
   *
   * @params string $string
   *    The string to search for.
   */
  public function search($string) {
    // You'd think we should URL encode this, but that makes it fail on
    // "The hitchhiker's guide to the galaxy".
    $this->open(['string' => $string]);
  }

  /**
   * SearchForCertainSize.
   *
   * @param string $interval
   *    Interval f.ex. '50-100'.
   * @param string $listOfTerms
   *    Search delimiters, f.ex. "term.creator=Agnes*".
   * @param string $publishedBetween
   *    Published year interval, f.ex. "2009-2017".
   *
   * @return string
   *    Nonempty if failure.
   */
  public function searchForCertainSize($interval, $listOfTerms, $publishedBetween) {
    // Start by making some syntax analysis.
    $stdmsg = "You must give an interval, like '50-100'";
    $lInterval = explode('-', $interval);
    if (count($lInterval) != 2) {
      return $stdmsg . " for requested size. To values separated with a dash, please.";
    }
    if (!is_numeric($lInterval[0]) || !is_numeric($lInterval[1])) {
      return $stdmsg . " for requested size. You haven't given numeric values.";
    }
    $lPublished = explode('-', $publishedBetween);
    if (count($lPublished) != 2) {
      return $stdmsg . " for published date. Two numeric values with a dash between, please.";
    }
    if (!is_numeric($lPublished[0]) || !is_numeric($lPublished[1])) {
      return $stdmsg . " for published date. You haven't given numeric values.";
    }
    // Add a preceeding "and" to the terms so we can search with them.
    $listOfTerms = (strlen($listOfTerms) > 0) ? ' and ' . $listOfTerms : '';
    // We start in the first year.
    // The variables lHigh* are upper limits, lLow* are lower limits. llast* holds the currently used interval.
    $lHighYear = $lPublished[0];
    $lLowYear = $lPublished[0];

    $llastLow = $lLowYear;
    $llastHigh = $lHighYear;

    // Now do a search.
    $this->open(['string' => urlencode("term.date>=" . $lLowYear . " and term.date<=" . $lHighYear . $listOfTerms)]);

    // Find out how many we got and log it for the tester, to see what is going on.
    $hits = $this->getShownSizeOfSearchResult();
    $this->logMsg(true, "[" . $lLowYear . ";" . $lHighYear . "]=" . $hits . " resultater\n");

    /*
     * we want to just try max 25 times to get a suitable size of search result .
     * If it takes this long we either have a huge interval to search
     * or we have searched ourselves into a rathole, so then it's time to end searching by then.
     */
    $attempts = 25;
    while (--$attempts > 0 && ($hits < $lInterval[0] || $hits > $lInterval[1])) {
      if ($hits < $lInterval[0]) {
        // Add a year because we found less than what we want:
        // First attempt to raise the upper year.. and else we try to decrease the lower year.
        if ($lHighYear < $lPublished[1]) {
          // We add around half of what we can - this is a binary search method. We could also just add one,
          // But tests have shown that this gives the result we want in less searches.
          $lHighYear = $lHighYear + intdiv($lPublished[1] - $lHighYear, 2);
        }
        else {
          // Alright - we are here because the upper year is already at the end of the interval we search within.
          // So now we try to lower the lower year.
          if ($lLowYear > $lPublished[0]) {
            $lLowYear--;
          }
          else {
            // By now we have to throw in the towel..
            return "Tried the entire interval, but couldn't find a suitable search result size.";
          }
        }
      }
      // Now see if we get more hits than we wanted. In that case, we will try to move the lower year up,
      // While 'pushing' the upper year with it so they never cross.
      if ($hits > $lInterval[0]) {
        // Move up lower year - again, binary search style, but taking into account that the difference between
        // the upper and lower may be just one year.
        $lLowYear = ($lHighYear - $lLowYear > 1) ? $lLowYear + intdiv($lHighYear - $lLowYear, 2) : $lLowYear + 1;
        // Now fix the upper year so upper is always >= lower.
        $lHighYear = ($lHighYear < $lLowYear) ? $lLowYear : $lHighYear;
      }
      // If we moved the lower year beyond the upper interval we search within, we throw in the towel.
      if ($lLowYear > $lPublished[1]) {
        return "Tried all the years in given interval, without finding a result of the requested size.";
      }
      // Do another search, unless this search is identical to the one we just did.
      if (!($llastHigh == $lHighYear && $llastLow == $lLowYear)) {
        // Save these years as new last-tries.
        $llastLow = $lLowYear;
        $llastHigh = $lHighYear;
        $this->open(['string' => urlencode("term.date>=" . $lLowYear . " and term.date<=" . $lHighYear . $listOfTerms)]);

        $hits = $this->getShownSizeOfSearchResult();
        $this->logMsg(true, "[" . $lLowYear . ";" . $lHighYear . "]=" . $hits . " resultater\n");
      }
    }

    // So - how did this go then?
    if ($hits <= $lInterval[1] && $hits >= $lInterval[0]) {
      // Success... we have a result within the limits.
      return "";
    }
    else {
      return "Could not find a search result within the limits. Adjust the criteria.";
    }
  }

  /**
   * SearchOnHomePage.
   *
   * @return string
   *    Nonempty if failure.
   *
   * @throws Exception
   *    In case of error.
   */
  public function searchOnHomePage() {
    // Find the radio button and activate it.
    $xpath = "//div[@id='edit-searches']//label[@class='option' and @for='edit-searches-node']/a";
    $found = $this->find('xpath', $xpath);
    if (!$found) {
      return "Could not find radio-button for searching on homepage.";
    }
    $this->scrollTo($found);
    $found->click();
    $this->waitForPage();
    return "";
  }

  /**
   * SetMax Page Traversals.
   *
   * @param int $maxPages
   *    The maximum number of search pages that will be traversed while searching for results.
   */
  public function setMaxPageTraversals($maxPages) {
    $this->maxPageTraversals = $maxPages;
  }

  /**
   * Sets number of search items per page via dropdown.
   *
   * @param int $size
   *    The number of items per page to set.
   *
   * @return string
   *    Returns non-empty string if failing.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *    In case of error.
   */
  public function setTheNumberOfResultsPerPageToSize($size) {
    $found = $this->find('css', 'select#edit-size.form-select[name="size"]');
    if (!$found) {
      return "Did not find a dropdown for setting results per page.";
    }
    $found->selectOption($size, false);
    $this->waitForPage();
    return "";
  }

  /**
   * Set Verbose Search Result mode.
   *
   * @param string $onoff
   *    Contains either 'on' or 'off'.
   */
  public function setVerboseSearchResult($onoff) {
    $this->verboseSearchResults = $onoff;
  }

  /**
   * Sorting.
   *
   * @param string $sortOption
   *    What to sort on.
   *
   * @return string
   *    Nonempty if failure.
   *
   * @throws Exception
   *    In case of error.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *    In case of error.
   */
  public function sort($sortOption) {
    // Check we're looking at a search result page.
    $page = $this->find('css', 'div.search-results li.list-item');
    if (null === $page) {
      return "Attempting sort when not on a search result page with results found.";
    }
    // Then we select the sorting from the dropdown.
    $sortDD = $this->find('css', 'select.form-select[name="sort"]');
    if (null === $sortDD) {
      return "Attempting sort but couldn't locate sorting dropdown. (css='select.form-select[name=\"sort\"]').";
    }

    // Now set the sortOption.
    $this->scrollTo($sortDD);
    // The second parameter is 'false' as it means we only select one value.
    $sortDD->selectOption($sortOption, false);

    return "";
  }

  /**
   * SortOptionValid - checks if it is.
   *
   * @param string $sortOption
   *    What to sort on.
   *
   * @return string
   *    Nonempty if the sortoption is not valid.
   */
  public function sortOptionValid($sortOption) {
    // Anticipate error.
    $isValid = false;
    $isValid = ($sortOption == "title_ascending");
    $isValid = ($sortOption == "title_descending") ? true : $isValid;
    $isValid = ($sortOption == "creator_ascending") ? true : $isValid;
    $isValid = ($sortOption == "creator_descending") ? true : $isValid;
    $isValid = ($sortOption == "date_ascending") ? true : $isValid;
    $isValid = ($sortOption == "date_descending") ? true : $isValid;
    if (!$isValid) {
      return "Error: you ask to sort on unknown criteria: " . $sortOption;
    }

  }

  /**
   * Unpack FacetList - clicks on the plus to expand the list.
   *
   * @throws Exception
   *    In case of error.
   */
  private function unpackFacetLists() {
    // Unpack all lists.
    // Notice we can't use getElement because it is not a problem if we don't have to unpack lists.
    $found = $this->find('css', 'a.expand-more');
    // We set 200 as the upper limit for unpacking. It's way above what we will experience,
    // But we want to unpack them all, and still get out of this in a civilised way if there unpacking doesn't work.
    $cnt = 200;
    while ($found && --$cnt > 0) {
      $this->scrollTo($found);

      try {
        $found->click();
      }
      catch (\Exception $e) {
        // If we end here, it's likely something is blocking for the control.
        // We can try to scroll a bit more, try to recapture and click again.
        $this->scrollABit(200);
        $found = $this->find('css', 'a.expand-more');
        // From a testing perspective we would like to know that we needed more scrolling.
        // This is an indication that the page is not shown as expected.
        $this->logMsg(true, "scrolling a bit extra..");
        $found->click();
      }
      // We allow the javascript to run.
      sleep(1);
      // Search again for the next.
      $found = $this->find('css', 'a.expand-more');
    }
  }

  /**
   * Finds all used facets, figure which will give the largest subset and click it.
   *
   * @return string
   *    Empty if all ok.
   */
  public function useFacetsToIncreaseSearchResults() {
    // Report an error if we've not set a facet correctly previously.
    if ($this->getExpectedSearchResultSize() == 0) {
      return "We can only attempt to deselect a facet if we selected one first.";
    }

    // Then find all the checked facets on the page.
    $found = $this->findAll('css', '.selected-checkbox');
    if (!$found) {
      return "Did not find any selected facets. We expect facets to contain class=selected-checkbox.";
    }

    return $this->getLargestFacetAndClickIt($found, false);
  }


  /**
   * Unpacks and selects the facet with the largest subset if selected.
   *
   * @return string
   *    Nonempty if failure.
   *
   * @throws Exception
   *    In case of error.
   */
  public function useFacetsToReduceSearchResultsToTheHighestPossible() {
    // First we need to unpack the list, so we can see all facets.
    $this->unpackFacetLists();

    // Now find all the checkboxes on the page.
    $found = $this->findAll('css', '.unselected-checkbox');
    if (!$found) {
      return "Didn't find any facets on the page.";
    }

    return $this->getLargestFacetAndClickIt($found, true);
  }
}
