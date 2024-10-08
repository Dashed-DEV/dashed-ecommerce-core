<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Livewire\WithPagination;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\Products;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ShowProducts extends Component
{
    use WithPagination;

    private $products = null;
    private $filters = null;
    public ?ProductCategory $productCategory = null;
    public ?string $pagination = null;
    public ?string $orderBy = null;
    public ?string $order = null;
    public ?string $sortBy = null;

    #[Url(except: '')]
    public $search = '';

    public array $priceSlider = [];
    public array $defaultSliderOptions = [];
    #[Url]
    public int $page = 1;

    public array $activeFilters = [];
    public array $activeFilterQuery = [];
    public array $usableFilters = [];
    public bool $enableFilters = true;

    public $event = [];

    protected function queryString()
    {
        return array_merge([
            'search' => ['except' => ''],
            'sortBy' => ['except' => ''],
            'page' => ['except' => 1],
            'activeFilters' => $this->activeFilters,
        ], []);
    }

    public function mount($productCategory = null, $enableFilters = true)
    {
        $this->productCategory = $productCategory;

        $this->pagination = request()->get('pagination', Customsetting::get('product_default_amount_of_products', null, 12));
        $this->sortBy = request()->get('sort-by', Customsetting::get('product_default_order_type', null, 'price'));
        $this->order = request()->get('order', Customsetting::get('product_default_order_sort', null, 'DESC'));
        $this->search = request()->get('search');
        $this->enableFilters = $enableFilters;

        $activeFilters = request()->get('activeFilters', []);
        foreach ($activeFilters as $filterKey => $activeFilter) {
            foreach ($activeFilter as $optionKey => $value) {
                if (! $value) {
                    unset($activeFilters[$filterKey][$optionKey]);
                } else {
                    $activeFilters[$filterKey][$optionKey] = true;
                }
            }
        }
        $this->activeFilters = $activeFilters;

        $this->loadProducts(true);
    }

    public function updated()
    {
        $this->page = 1;
        $this->loadProducts();
    }

    public function updatedPage($page)
    {
        $this->page = $page;
        $this->loadProducts();
    }

    public function loadProducts(bool $isMount = false)
    {
        //        if (!$this->products) {

        $activeFilterQuery = [];
        $usableFilters = [];
        foreach ($this->activeFilters as $filterKey => $filterValues) {
            foreach ($filterValues as $valueKey => $valueActivated) {
                if ($valueActivated) {
                    $activeFilterQuery['activeFilters'][$valueKey] = ['except' => ''];
                }
            }
        }

        $this->activeFilterQuery = $activeFilterQuery;

        request()->replace(array_merge([
            'search' => $this->search,
            'sortBy' => $this->sortBy,
            'page' => request()->get('page'),
            'activeFilters' => $this->activeFilters,
        ], []));

        $response = Products::getAllV2($this->pagination, $this->page, $this->sortBy, $this->order, $this->productCategory->id ?? null, $this->search, $this->activeFilters, $this->priceSlider, $this->enableFilters);

        $this->products = $response['products'];
        $this->filters = $response['filters'] ?? [];

        $this->defaultSliderOptions = [
            'start' => [
                (float)$response['minPrice'],
                (float)$response['maxPrice'],
            ],
            'range' => [
                'min' => [(float)$response['minPrice']],
                'max' => [(float)$response['maxPrice']],
            ],
            'connect' => true,
            'behaviour' => 'tap-drag',
            'tooltips' => true,
            'step' => 1,
        ];
        //        }
    }

    public function setSortByValue($value)
    {
        $this->sortBy = $value;
        $this->loadProducts();
    }

    public function removeFilter($filterKey, $optionKey)
    {
        $this->activeFilters[$filterKey][$optionKey] = false;
        $this->loadProducts();
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.products.show-products', [
            'products' => $this->products,
            'filters' => $this->filters,
            'activeFilters' => $this->activeFilters,
        ]);
    }
}
