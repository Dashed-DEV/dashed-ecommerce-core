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
    public ?string $search = '';
    public array $priceSlider = [];
    public array $defaultSliderOptions = [];

    public array $activeFilters = [];
    public array $activeFilterQuery = [];
    public array $usableFilters = [];

    public $event = [];

    public function getQueryString()
    {
        return array_merge([
            'search' => ['except' => ''],
            'sortBy' => ['except' => ''],
            'page' => ['except' => 1],
            'activeFilters' => $this->activeFilters,
        ], []);
    }

    public function mount(?ProductCategory $productCategory = null)
    {
        $this->productCategory = $productCategory;

        $this->pagination = request()->get('pagination', Customsetting::get('product_default_amount_of_products', null, 12));
        $this->orderBy = request()->get('order-by', Customsetting::get('product_default_order_type', null, 'price'));
        $this->order = request()->get('order', Customsetting::get('product_default_order_sort', null, 'DESC'));
        $this->sortBy = request()->get('sortBy', 'default');

        $activeFilters = request()->get('activeFilters', []);
        foreach ($activeFilters as $filterKey => $activeFilter) {
            foreach ($activeFilter as $optionKey => $value) {
                if (! $value) {
                    unset($activeFilters[$filterKey][$optionKey]);
                }
            }
        }
        $this->activeFilters = $activeFilters;

        $this->loadProducts();
    }

    public function updated()
    {
        $this->loadProducts();
    }

    public function loadProducts()
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

        $response = Products::getAllV2($this->pagination, $this->sortBy, $this->productCategory->id ?? null, $this->search, $this->activeFilters);
        $this->products = $response['products'];
        $this->filters = $response['filters'];

        $this->defaultSliderOptions = [
            'start' => [
                (float)$response['minPrice'],
                (float)$response['maxPrice']
            ],
            'range' => [
                'min' =>  [(float)$response['minPrice']],
                'max' => [(float)$response['maxPrice']]
            ],
            'connect' => true,
            'behaviour' => 'tap-drag',
            'tooltips' => true,
            'step' => 1,
        ];
        //        }
    }

    public function render()
    {

        return view('dashed-ecommerce-core::frontend.products.show-products', [
            'products' => $this->products,
            'filters' => $this->filters,
            'activeFilters' => $this->activeFilters,
        ]);
    }
}
