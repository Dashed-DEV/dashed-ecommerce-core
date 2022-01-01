<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\Cache;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilter;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class Products
{
    public static function get($limit = 4, $orderBy = 'created_at', $order = 'DESC', $topLevelProductOnly = false)
    {
        //Todo: change these params above into variables to make it flexible
        $orderByRequest = request()->get('sort-by');
        if ($orderByRequest) {
            if ($orderByRequest == 'price-asc') {
                $orderBy = 'price';
                $order = 'ASC';
            } elseif ($orderByRequest == 'price-desc') {
                $orderBy = 'price';
                $order = 'DESC';
            } elseif ($orderByRequest == 'most-sold') {
                $orderBy = 'purchases';
                $order = 'DESC';
            } elseif ($orderByRequest == 'stock') {
                $orderBy = 'stock';
                $order = 'DESC';
            } elseif ($orderByRequest == 'newest') {
                $orderBy = 'created_at';
                $order = 'DESC';
            } elseif ($orderByRequest == 'order') {
                $orderBy = 'order';
                $order = 'ASC';
            }
        }

        $products = Product::search()->thisSite()->publicShowable()->limit($limit)->orderBy($orderBy, $order)->with(['parentProduct']);
        if ($topLevelProductOnly) {
            //publicShowable stops the childProducts from showing
            $products->topLevel();
        }
        $products = $products->get();

        foreach ($products as $product) {
            if ($product->parentProduct && $product->parentProduct->only_show_parent_product) {
                $product->name = $product->parentProduct->name;
            }
        }

        return $products;
    }

    public static function getAll($pagination = 12, $orderBy = 'created_at', $order = 'DESC', $categoryId = null)
    {
        $orderByRequest = request()->get('sort-by');
//        return Cache::tags(['products'])->rememberForever("products-all-$pagination-$orderBy-$order-$categoryId-$orderByRequest-" . request()->get('page', 1), function () use ($pagination, $orderBy, $order, $categoryId, $orderByRequest) {
        if ($orderByRequest) {
            if ($orderByRequest == 'price-asc') {
                $orderBy = 'price';
                $order = 'ASC';
            } elseif ($orderByRequest == 'price-desc') {
                $orderBy = 'price';
                $order = 'DESC';
            } elseif ($orderByRequest == 'most-sold') {
                $orderBy = 'purchases';
                $order = 'DESC';
            } elseif ($orderByRequest == 'stock') {
                $orderBy = 'stock';
                $order = 'DESC';
            } elseif ($orderByRequest == 'newest') {
                $orderBy = 'created_at';
                $order = 'DESC';
            } elseif ($orderByRequest == 'default') {
                $orderBy = 'order';
                $order = 'ASC';
            }
        } else {
            if ($orderBy == 'default') {
                $orderBy = 'order';
                $order = 'ASC';
            }
        }

        $productFilters = self::getFilters();
        $hasActiveFilters = false;
        foreach ($productFilters as $productFilter) {
            foreach ($productFilter->productFilterOptions as $option) {
                if ($option->checked) {
                    $hasActiveFilters = true;
                }
            }
        }

        $correctProductIds = [];
        if ($categoryId && $category = ProductCategory::with(['products'])->findOrFail($categoryId)) {
            $allProducts = $category->products()->search()->thisSite()->publicShowable()->orderBy($orderBy, $order)->with(['productFilters', 'productCategories'])->get();
        } else {
            $allProducts = Product::search()->thisSite()->publicShowable()->orderBy($orderBy, $order)->with(['productFilters', 'productCategories'])->get();
        }

        $onlyShowParentIds = [];
        foreach ($allProducts as $product) {
            $productIsValid = true;
            if ($hasActiveFilters) {
                foreach ($productFilters as $productFilter) {
                    $productValidForFilter = false;
                    $filterIsActive = false;
                    foreach ($productFilter->productFilterOptions as $option) {
                        if ($option->checked) {
                            $filterIsActive = true;
                            if (! $productValidForFilter) {
                                if ($product->productFilters()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $option->id)->exists()) {
                                    $productValidForFilter = true;
                                }
                            }
                        }
                    }
                    if ($filterIsActive && ! $productValidForFilter) {
                        $productIsValid = false;
                    }
                }
            }

            if ($productIsValid && $product->parentProduct && $product->parentProduct->only_show_parent_product) {
                if (in_array($product->parentProduct->id, $onlyShowParentIds)) {
                    $productIsValid = false;
                } else {
                    $onlyShowParentIds[] = $product->parentProduct->id;
                }
            }

            if ($productIsValid) {
                $correctProductIds[] = $product->id;
            }
        }

        $products = Product::whereIn('id', $correctProductIds)->search()->thisSite()->publicShowable()->orderBy($orderBy, $order)->with(['productFilters', 'shippingClasses', 'productCategories', 'parentProduct', 'media'])->paginate($pagination)->withQueryString();

        foreach ($products as $product) {
            if ($product->parentProduct && $product->parentProduct->only_show_parent_product) {
                $product->name = $product->parentProduct->name;
            }
        }

        return [
            'products' => $products,
            'filters' => self::getFilters($allProducts->pluck('id')),
        ];
//        });
    }

    public static function getFilters($products = [])
    {
        $productFilters = ProductFilter::with(['productFilterOptions', 'productFilterOptions.products'])->where('hide_filter_on_overview_page', 0)->orderBy('created_at')->get();
        foreach ($productFilters as $productFilter) {
            $filterHasActiveOptions = false;
            $results = request()->get(str_replace(' ', '_', $productFilter->name));
            foreach ($productFilter->productFilterOptions as $option) {
                if ($results && in_array($option->name, $results)) {
                    $option->checked = true;
                } else {
                    $option->checked = false;
                }
                $option->resultCount = 0;
                if ($products) {
                    $option->resultCount = $option->resultCount + $option->products()->whereIn('product_id', $products)->count();
                    if (! $filterHasActiveOptions && $option->resultCount > 0) {
                        $filterHasActiveOptions = true;
                    }
                }
            }
            $productFilter->hasActiveOptions = $filterHasActiveOptions;
        }

        return $productFilters;
    }

    public static function getHighestPrice()
    {
        $highestPrice = 0;
        $products = Product::thisSite()->publicShowable()->with(['productFilters', 'shippingClasses', 'productCategories'])->get();
        if ($products) {
            foreach ($products as $product) {
                if ($product->currentPrice > $highestPrice) {
                    $highestPrice = $product->currentPrice;
                }
            }
        } else {
            $highestPrice = 100;
        }

        return number_format($highestPrice, 0, '', '');
    }

    public static function getById($productId)
    {
        return Product::thisSite()->publicShowable()->where('id', $productId)->with(['productFilters', 'shippingClasses', 'productCategories'])->first();
    }
}