<?php

namespace ipl\Pagination;

use Icinga\Web\Url;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Pagination\Adapter\AdapterInterface;

/**
 * The paginator displays a list of links that point to different pages of the current view
 *
 * The default HTML markup (tag and attributes) for the paginator look like the following:
 * <div class="pagination-control" role="navigation">...</div>
 *
 * @TODO(el): Remove Icinga\Web\Url dependency
 */
class Paginator extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'pagination-control', 'role' => 'navigation'];

    /** @var AdapterInterface The pagination adapter which handles the underlying data source */
    protected $adapter;

    /** @var Url The URL to base off pagination URLs */
    protected $url;

    /** @var int Default maximum number of items which should be shown per page */
    protected $defaultPageSize = 25;

    /** @var string Name of the URL parameter which stores the current page number */
    protected $pageParam = 'page';

    /** @var string Name of the URL parameter which holds the page size. If given, overrides {@link $defaultPageSize} */
    protected $pageSizeParam = 'limit';

    /** @var string */
    protected $pageSpacer = '...';

    /** @var int Cache for the total number of items */
    private $totalCount;

    /**
     * Create a paginator
     *
     * @param   AdapterInterface    $adapter        The pagination adapter
     * @param   Url                 $url            The URL to base off paging URLs
     */
    public function __construct(AdapterInterface $adapter, Url $url)
    {
        $this->adapter = $adapter;
        $this->url = $url;

        $adapter->limit($this->getLimit());
        $adapter->offset($this->getOffset());
    }

    /**
     * Get the default page size
     *
     * @return  int The default page size
     */
    public function getDefaultPageSize()
    {
        return $this->defaultPageSize;
    }

    /**
     * Set the default page size
     *
     * @param   int $defaultPageSize    The default page size
     *
     * @return  $this
     */
    public function setDefaultPageSize($defaultPageSize)
    {
        $this->defaultPageSize = (int) $defaultPageSize;

        return $this;
    }

    /**
     * Get the name of the URL parameter which stores the current page number
     *
     * @return  string  The name of URL parameter which stores the current page number
     */
    public function getPageParam()
    {
        return $this->pageParam;
    }

    /**
     * Set the name of the URL parameter which stores the current page number
     *
     * @param   string  $pageParam  The name of the URL parameter which stores the current page number
     *
     * @return  $this
     */
    public function setPageParam($pageParam)
    {
        $this->pageParam = $pageParam;

        return $this;
    }

    /**
     * Get the name of the URL parameter which stores the page size
     *
     * @return  string  The name of the URL parameter which stores the page size
     */
    public function getPageSizeParam()
    {
        return $this->pageSizeParam;
    }
    /**
     * Set the name of the URL parameter which stores the page size
     *
     * @param   string  $pageSizeParam  The name of the URL parameter which stores the page size
     *
     * @return  $this
     */
    public function setPageSizeParam($pageSizeParam)
    {
        $this->pageSizeParam = $pageSizeParam;

        return $this;
    }

    /**
     * Get the total number of items
     *
     * @return  int
     */
    public function getTotalCount()
    {
        if ($this->totalCount === null) {
            $this->totalCount = $this->adapter->count();
        }

        return $this->totalCount;
    }

    /**
     * Get the current page number
     *
     * @return  int
     */
    public function getCurrentPageNumber()
    {
        return (int) $this->url->getParam($this->pageParam, 1);
    }

    /**
     * Get the configured page size
     *
     * @return  int
     */
    public function getPageSize()
    {
        return (int) $this->url->getParam($this->pageSizeParam, $this->defaultPageSize);
    }

    /**
     * Get the total page count
     *
     * @return  int
     */
    public function getPageCount()
    {
        $pageSize = $this->getPageSize();

        if ($pageSize === 0) {
            return 0;
        }

        if ($pageSize < 0) {
            return 1;
        }

        return ceil($this->getTotalCount() / $pageSize);
    }

    /**
     * Get the limit
     *
     * Use this method to set the LIMIT part of a query for fetching the current page.
     *
     * @return  int If the page size is infinite, -1 will be returned
     */
    public function getLimit()
    {
        $pageSize = $this->getPageSize();

        return $pageSize < 0 ? -1 : $pageSize;
    }

    /**
     * Get the offset
     *
     * Use this method to set the OFFSET part of a query for fetching the current page.
     *
     * @return  int
     */
    public function getOffset()
    {
        $currentPageNumber = $this->getCurrentPageNumber();
        $pageSize = $this->getPageSize();

        return $currentPageNumber <= 1 ? 0 : ($currentPageNumber - 1) * $pageSize;
    }

    /**
     * Create a URL for paging from the given page number
     *
     * @param   int $page       The page number
     * @param   int $pageSize   The number of items per page. If you want to stick to the defaults,
     *                          don't set this parameter
     *
     * @return  Url The URL for paging
     */
    public function createUrl($page, $pageSize = null)
    {
        $params = [$this->getPageParam() => $page];

        if ($pageSize !== null) {
            $params[$this->getPageSizeParam()] = $pageSize;
        }

        return $this->url->with($params);
    }

    /**
     * Get the pages to render links for
     *
     * @return  array
     */
    public function getPages()
    {
        $pageCount = $this->getPageCount();

        if ($pageCount < 2) {
            return [];
        }

        if ($pageCount <= 10) {
            // If there are less than or exactly 10 pages, show them all
            return range(1, $pageCount);
        }

        $currentPageNumber = $this->getCurrentPageNumber();

        if ($currentPageNumber <= 5) {
            // Show the first 7 and the last two pages if we are on page 1-5
            $range = range(1, 7);
            $range[] = $this->pageSpacer;
            $range[] = $pageCount - 1;
            $range[] = $pageCount;

            return $range;
        }

        $range = range(1, 2);

        if ($currentPageNumber >= $pageCount - 5) {
            // Show the first 2 and the last 6 pages if we are on one of the last 5 pages
            $range[] = $this->pageSpacer;

            return array_merge($range, range($pageCount - 6, $pageCount));
        }

        // Show the first 2, the last 2 and 4 pages in between
        $range[] = $this->pageSpacer;

        return array_merge(
            $range,
            range($currentPageNumber - 1, $currentPageNumber + 2),
            [$this->pageSpacer, $pageCount - 1, $pageCount]
        );
    }

    public function assemble()
    {
        $pageCount = $this->getPageCount();

        if ($pageCount < 2) {
            return;
        }

        $currentPageNumber = $this->getCurrentPageNumber();
        $pageSize = $this->getPageSize();
        $totalCount = $this->getTotalCount();

        // Accessibility info
        $this->add(Html::tag(
            'h2',
            [
                'id'       => 'pagination',
                'class'    => 'sr-only',
                'tabindex' => '-1'
            ],
            $this->translate('Pagination')
        ));

        $paginator = Html::tag('ul', ['class' => 'tab-nav nav']);

        $this->add($paginator);

        $prevIcon = Html::tag('i', ['class' => 'icon-angle-double-left']);

        if ($currentPageNumber > 1) {
            $prevItem = Html::tag('li', ['class' => 'previous-page nav-item']);

            $label = sprintf(
                $this->translate('Show rows %u to %u of %u'),
                ($currentPageNumber - 2) * $pageSize + 1,
                ($currentPageNumber - 1) * $pageSize,
                $totalCount
            );

            $prevItem->add(Html::tag(
                'a',
                [
                    'title'       => $label,
                    'href'        => $this->createUrl($currentPageNumber - 1),
                    'arial-label' => $label
                ],
                $prevIcon
            ));
        } else {
            $prevItem = Html::tag(
                'li',
                ['class' => 'previous-page nav-item disabled', 'aria-hidden' => true]
            );

            $prevItem->add([
                Html::tag('span', ['class' => 'sr-only'], $this->translate('Previous page')),
                $prevIcon
            ]);
        }

        $paginator->add($prevItem);

        foreach ($this->getPages() as $page) {
            // HTML attributes for the HTML link element
            $linkAttributes = new Attributes(['class' => ['nav-item']]);

            switch ($page) {
                case $this->pageSpacer:
                    $content = $page;
                    $linkAttributes->add('class', 'disabled');
                    break;
                /** @noinspection PhpMissingBreakStatementInspection */
                case $currentPageNumber:
                    $linkAttributes->add('class', 'active');
                // Move to default
                default:
                    $content = Html::tag('a', ['href' => $this->createUrl($page)], $page);
            }

            $paginator->add(Html::tag('li', $linkAttributes, $content));
        }

        $nextIcon = Html::tag('i', ['class' => 'icon-angle-double-right']);

        if ($currentPageNumber < $pageCount) {
            $nextItem = Html::tag('li', ['class' => 'next-page nav-item']);

            $label = sprintf(
                $this->translate('Show rows %u to %u of %u'),
                $currentPageNumber * $pageSize + 1,
                ($currentPageNumber + 1) * $pageSize,
                $totalCount
            );

            $nextItem->add(Html::tag(
                'a',
                [
                    'title'       => $label,
                    'href'        => $this->createUrl($currentPageNumber + 1),
                    'arial-label' => $label
                ],
                $nextIcon
            ));
        } else {
            $nextItem = Html::tag('li', ['class' => 'next-page nav-item disabled', 'aria-hidden' => true]);

            $nextItem->add([
                Html::tag('span', ['class' => 'sr-only'], $this->translate('Next page')),
                $nextIcon
            ]);
        }

        $paginator->add($nextItem);
    }

    /** @TODO(el): Use ipl-translation when it's ready instead */
    public function translate($message)
    {
        return $message;
    }
}
