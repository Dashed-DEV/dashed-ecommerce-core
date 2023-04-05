<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Qubiqx\QcommerceCore\Classes\Sites;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Traits\HasDynamicRelation;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceCore\Models\Concerns\IsVisitable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Qubiqx\QcommerceEcommerceCore\Events\Products\ProductCreatedEvent;

class Product extends Model
{
    use SoftDeletes;
    use HasDynamicRelation;
    use IsVisitable;

    protected $table = 'qcommerce__products';

    public $translatable = [
        'name',
        'slug',
        'short_description',
        'description',
        'search_terms',
        'content',
        'images',
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'expected_in_stock_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $with = [
        'productFilters',
        'parent',
    ];

    protected $casts = [
        'site_ids' => 'array',
        'images' => 'array',
    ];

    protected static function booted()
    {
        static::created(function ($product) {
            ProductCreatedEvent::dispatch($product);
        });

        static::saved(function ($product) {
            Cache::tags(['products', 'product-' . $product->id])->flush();
            if ($product->parent) {
                Cache::tags(['product-' . $product->parent->id])->flush();
                foreach ($product->parent->childProducts as $childProduct) {
                    Cache::tags(['product-' . $childProduct->id])->flush();
                }
            }

            if ($product->is_bundle && $product->type == 'variable' && ! $product->parent_id) {
                $product->is_bundle = false;
                $product->save();
                $product->bundleProducts()->detach();
            }
        });

        static::deleting(function ($product) {
            foreach ($product->childProducts as $childProduct) {
                $childProduct->delete();
            }
            $product->productCategories()->detach();
            $product->productFilters()->detach();
            $product->activeProductFilters()->detach();
        });
    }

    public function scopeSearch($query, ?string $search = null)
    {
        $minPrice = request()->get('min-price') ? request()->get('min-price') : null;
        $maxPrice = request()->get('max-price') ? request()->get('max-price') : null;

        $search = request()->get('search') ?: $search;

        if ($minPrice) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }

        $query->where(function ($query) use ($search) {
            $loop = 1;
            foreach (self::getTranslatableAttributes() as $attribute) {
                if ($loop == 1) {
                    $query->whereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
                } else {
                    $query->orWhereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
                }
                $loop++;
            }
        });
    }

    public function scopePublic($query)
    {
        $query->where('public', 1);
    }

    public function scopeIsNotBundle($query)
    {
        $query->where('is_bundle', 0);
    }

    public function scopeIsBundle($query)
    {
        $query->where('is_bundle', 1);
    }

    public function scopeNotParentProduct($query)
    {
        $query->where(function ($query) {
            $query->where('type', '!=', 'variable');
        })->orWhere(function ($query) {
            $query->where('type', 'variable')
                ->where('parent_id', '!=', null);
        });
    }

    public function scopePublicShowable($query, bool $overridePublic = false)
    {
        if (auth()->guest() || (auth()->check() && auth()->user()->role !== 'admin' && $overridePublic)) {
            $query
                ->public()
                ->thisSite()
                ->where('sku', '!=', null)
                ->where('price', '!=', null)
                ->notParentProduct()
                ->where(function ($query) {
                    $query->where('start_date', null);
                })->orWhere(function ($query) {
                    $query->where('start_date', '<=', Carbon::now());
                })->where(function ($query) {
                    $query->where('end_date', null);
                })->orWhere(function ($query) {
                    $query->where('end_date', '>=', Carbon::now());
                });
        }
    }

    public function scopeHandOrderShowable($query)
    {
        $query
            ->where(function ($query) {
                $query->where('type', '!=', 'variable')
                    ->where('sku', '!=', null)
                    ->where('price', '!=', null)
                    ->public();
            })->orWhere(function ($query) {
                $query->where('type', 'variable')
                    ->where('parent_id', '!=', null)
                    ->where('sku', '!=', null)
                    ->where('price', '!=', null)
                    ->public();
            })->where(function ($query) {
                $query->where('start_date', null);
            })->orWhere(function ($query) {
                $query->where('start_date', '<=', Carbon::now());
            })->where(function ($query) {
                $query->where('end_date', null);
            })->orWhere(function ($query) {
                $query->where('end_date', '>=', Carbon::now());
            });
    }

    public function scopeTopLevel($query)
    {
        $query->where('parent_id', null);
    }

    public function scopeAvailableForShoppingFeed($query)
    {
        $query->where('ean', '!=', null)->whereIn('type', ['simple', 'variable']);
    }

    public function scopePushableToEfulfillmentShop($query)
    {
        $query->where('ean', '!=', null)->whereIn('type', ['simple', 'variable'])->where('efulfillment_shop_id', null)->thisSite();
    }

    public function breadcrumbs()
    {
        $breadcrumbs = [
            [
                'name' => $this->name,
                'url' => $this->getUrl(),
            ],
        ];

        $productCategory = $this->productCategories()->first();

        //Check if has child, to make sure all categories show in breadcrumbs
        while ($productCategory && $productCategory->getFirstChilds()->whereIn('id', $this->productCategories->pluck('id'))->first()) {
            $productCategory = $productCategory->getFirstChilds()->whereIn('id', $this->productCategories->pluck('id'))->first();
        }

        if ($productCategory) {
            while ($productCategory) {
                $breadcrumbs[] = [
                    'name' => $productCategory->name,
                    'url' => $productCategory->getUrl(),
                ];
                $productCategory = ProductCategory::find($productCategory->parent_id);
            }
        }

        return array_reverse($breadcrumbs);
    }

    public function getTotalPurchasesAttribute()
    {
        $purchases = $this->purchases;

        foreach ($this->childProducts as $childProduct) {
            $purchases = $purchases + $childProduct->purchases;
        }

        return $purchases;
    }

    public function getCurrentPriceAttribute()
    {
        if ($this->childProducts()->count()) {
            return $this->childProducts()->orderBy('price', 'ASC')->first()->price;
        } else {
            return $this->price;
        }
    }

    public function getDiscountPriceAttribute()
    {
        if ($this->childProducts()->count()) {
            return $this->childProducts()->orderBy('price', 'ASC')->first()->new_price;
        } else {
            if ($this->new_price) {
                return $this->new_price;
            } else {
                return null;
            }
        }
    }

    public function getFirstImageUrlAttribute()
    {
        return $this->allImages->first()['image'] ?? '';
    }

    public function getAllImagesAttribute()
    {
        return $this->images ? collect($this->images) : collect();
    }

    public function getAllImagesExceptFirstAttribute()
    {
        $images = $this->allImages;
        if (count($images)) {
            unset($images[0]);
        }

        return $images;
    }

    public function getUrl($locale = null)
    {
        if (! $locale) {
            $locale = App::getLocale();
        }

        if ($this->childProducts()->count()) {
            $url = $this->childProducts()->first()->getUrl();
        } else {
            $url = '/' . Translation::get('products-slug', 'slug', 'products') . '/' . $this->slug;
        }

        if ($locale != config('app.locale')) {
            $url = App::getLocale() . '/' . $url;
        }

        return LaravelLocalization::localizeUrl($url);
    }

    public function getStatusAttribute()
    {
        if (! $this->public) {
            return false;
        }

        if ($this->type == 'variable') {
            return true;
        }

        $active = false;
        if (! $this->start_date && ! $this->end_date) {
            $active = true;
        } else {
            if ($this->start_date && $this->end_date) {
                if ($this->start_date <= Carbon::now() && $this->end_date >= Carbon::now()) {
                    $active = true;
                }
            } else {
                if ($this->start_date) {
                    if ($this->start_date <= Carbon::now()) {
                        $active = true;
                    }
                } else {
                    if ($this->end_date >= Carbon::now()) {
                        $active = true;
                    }
                }
            }
        }
        if ($active) {
            if (! $this->sku || ! $this->price) {
                $active = false;
            }
        }
        if ($active) {
            if ($this->parent) {
                $active = $this->parent->public;
            }
        }

        return $active;
    }

    public function getCombinations($arrays)
    {
        $result = [[]];
        foreach ($arrays as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    public function filters()
    {
        $parentProduct = $this->parent;

        if ($parentProduct) {
            $childProducts = $parentProduct->childProducts()->publicShowable()->get();
            $activeFilters = $parentProduct->activeProductFiltersForVariations;
        } else {
            $childProducts = [
                $this,
            ];
            $activeFilters = $this->activeProductFiltersForVariations;
        }

        $showableFilters = [];
        $activeFiltersValues = [];

        foreach ($activeFilters as $activeFilter) {
            $filterOptionValues = [];
            foreach ($childProducts as $childProduct) {
                $filterName = '';
                $activeFilterId = '';
                $activeFilterOptionIds = [];
                $activeFilterOptions = [];

                foreach ($activeFilter->productFilterOptions as $option) {
                    if ($childProduct->productFilters()->where('product_filter_option_id', $option->id)->exists()) {
                        if ($filterName) {
                            $filterName .= ', ';
                            $activeFilterId .= '-';
                        }
                        $filterName .= $option->name;
                        $activeFilterId .= $option->id;
                        $activeFilterOptionIds[] = $option->id;
                        $activeFilterOptions[] = $option;
                    }
                }

                //If something does not work correct, check if below code makes sure there is a active one
                //Array key must be string, otherwise Livewire renders it in order of id, instead of order from filter option
                if (count($activeFilterOptionIds) && (! array_key_exists('filter-' . $activeFilterId, $filterOptionValues) || $this->id == $childProduct->id)) {
                    $filterOptionValues['filter-' . $activeFilterId] = [
                        'id' => $activeFilter->id,
                        'name' => $filterName,
                        'order' => $activeFilterOptions[0]->order,
                        'activeFilterOptionIds' => $activeFilterOptionIds,
                        'active' => $this->id == $childProduct->id,
                        'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
                        'productId' => ($this->id == $childProduct->id) ? $this->id : '',
                        'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                        'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                        'isPreOrder' => ($this->id == $childProduct->id) ? $this->isPreorderable() : false,
                    ];
                    if ($this->id == $childProduct->id) {
                        $activeFiltersValues['filter-' . $activeFilterId] = [
                            'id' => $activeFilter->id,
                            'name' => $filterName,
                            'activeFilterOptionIds' => $activeFilterOptionIds,
                            'active' => $this->id == $childProduct->id,
                            'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
                            'productId' => ($this->id == $childProduct->id) ? $this->id : '',
                            'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                            'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                            'isPreOrder' => ($this->id == $childProduct->id) ? ($this->isPreorderable()) : false,
                        ];
                    }
                }
            }

            $showableFilters[] = [
                'id' => $activeFilter->id,
                'name' => $activeFilter->name,
                'values' => $filterOptionValues,
            ];
        }

        foreach ($showableFilters as &$showableFilter) {
            $correctFilterOptions = 0;
            foreach ($showableFilter['values'] as &$showableFilterValue) {
                if (! $showableFilterValue['url']) {
                    foreach ($childProducts as $childProduct) {
                        if ($childProduct->id != $this->id) {
                            $productIsCorrectForFilter = true;
                            foreach ($showableFilterValue['activeFilterOptionIds'] as $activeFilterOptionId) {
                                if (! $childProduct->productFilters()->where('product_filter_option_id', $activeFilterOptionId)->exists()) {
                                    $productIsCorrectForFilter = false;
                                }
                            }
                            if ($productIsCorrectForFilter) {
                                foreach ($activeFiltersValues as $activeFilterValue) {
                                    if ($activeFilterValue['id'] != $showableFilterValue['id']) {
                                        $productHasCorrectFilterOption = true;
                                        foreach ($activeFilterValue['activeFilterOptionIds'] as $activeFilterOptionId) {
                                            if (! $childProduct->productFilters()->where('product_filter_option_id', $activeFilterOptionId)->exists()) {
                                                $productHasCorrectFilterOption = false;
                                            }
                                        }
                                        if (! $productHasCorrectFilterOption) {
                                            $productIsCorrectForFilter = false;
                                        }
                                    }
                                }
                            }
                            if ($productIsCorrectForFilter) {
                                $showableFilterValue['url'] = $childProduct->getUrl();
                                $showableFilterValue['productId'] = $childProduct->id;
                                $showableFilterValue['in_stock'] = $childProduct->inStock();
                                $showableFilterValue['inStock'] = $childProduct->inStock();
                                $showableFilterValue['isPreOrder'] = $childProduct->isPreorderable();
                                $correctFilterOptions++;
                            }
                        }
                    }
                } else {
                    $correctFilterOptions++;
                }
            }
            $showableFilter['correctFilterOptions'] = $correctFilterOptions;
        }

        foreach ($showableFilters as &$showableFilter) {
            $showableFilter['values'] = collect($showableFilter['values'])->sortBy('order');
        }

        return $showableFilters;
    }

    public function reservedStock()
    {
        return OrderProduct::where('product_id', $this->id)->whereIn('order_id', Order::whereIn('status', ['pending'])->pluck('id'))->count();
    }

    public function stock()
    {
        if ($this->use_stock) {
            if ($this->outOfStockSellable()) {
                return 100000;
            } else {
                return $this->stock - $this->reservedStock();
            }
        } else {
            if ($this->stock_status == 'in_stock') {
                return 100000;
            } else {
                return 0;
            }
        }
    }

    public function hasDirectSellableStock()
    {
        if ($this->childProducts()->count()) {
            foreach ($this->childProducts as $childProduct) {
                if ($childProduct->hasDirectSellableStock()) {
                    return true;
                }
            }
        } else {
            if ($this->directSellableStock() > 0) {
                return true;
            }
        }

        return false;
    }

    public function directSellableStock()
    {
        if ($this->use_stock) {
            return $this->stock - $this->reservedStock();
        } else {
            if ($this->stock_status == 'in_stock') {
                return 100000;
            } else {
                return 0;
            }
        }
    }

    public function inStock()
    {
        if ($this->childProducts()->count()) {
            foreach ($this->childProducts as $childProduct) {
                if ($childProduct->inStock()) {
                    return true;
                }
            }
        } elseif ($this->is_bundle) {
            $allBundleProductsInStock = true;

            foreach ($this->bundleProducts as $bundleProduct) {
                if (! $bundleProduct->inStock()) {
                    $allBundleProductsInStock = false;
                }
            }

            return $allBundleProductsInStock;
        } else {
            if ($this->type == 'simple') {
                return $this->stock() > 0;
            } elseif ($this->type == 'variable') {
                if ($this->parent) {
                    return $this->stock() > 0;
                } else {
                    foreach ($this->childProducts() as $childProduct) {
                        if ($childProduct->inStock()) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function outOfStockSellable()
    {
        //Todo: make editable if expectedInStockDateValid should be checked or not

        if (! $this->use_stock) {
            if ($this->stock_status == 'out_of_stock') {
                return false;
            }
        }

        if (! $this->out_of_stock_sellable) {
            return false;
        }

        if (Customsetting::get('product_out_of_stock_sellable_date_should_be_valid', Sites::getActive(), 1) && ! $this->expectedInStockDateValid()) {
            return false;
        }

        return true;
    }

    public function isPreorderable()
    {
        return $this->inStock() && ! $this->hasDirectSellableStock() && $this->use_stock;
    }

    public function expectedInStockDate()
    {
        return $this->expected_in_stock_date ? $this->expected_in_stock_date->format('d-m-Y') : null;
    }

    public function expectedInStockDateValid()
    {
        return $this->expected_in_stock_date >= now();
    }

    public function expectedInStockDateInWeeks()
    {
        $expectedInStockDate = self::expectedInStockDate();
        if (! $expectedInStockDate || Carbon::parse($expectedInStockDate) < now()) {
            return 0;
        }

        $diffInWeeks = Carbon::parse($expectedInStockDate)->diffInWeeks(now()->subDay());
        if ($diffInWeeks < 0) {
            $diffInWeeks = 0;
        }

        return $diffInWeeks;
    }

    public function purchasable()
    {
        if ($this->inStock() || $this->outOfStockSellable()) {
            return true;
        }

        return false;
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function childProducts()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function productCategories()
    {
        return $this->belongsToMany(ProductCategory::class, 'qcommerce__product_category');
    }

    public function suggestedProducts()
    {
        return $this->belongsToMany(Product::class, 'qcommerce__product_suggested_product', 'product_id', 'suggested_product_id');
    }

    public function shippingClasses()
    {
        return $this->belongsToMany(ShippingClass::class, 'qcommerce__product_shipping_class');
    }

    public function productFilters()
    {
        //        return Cache::tags(["product-$this->id"])->rememberForever("product-filters-" . $this->id, function () {
        //            return $this->productFiltersRelation();
        //        });
        return $this->belongsToMany(ProductFilter::class, 'qcommerce__product_filter')->orderBy('created_at')->withPivot(['product_filter_option_id']);
    }

    public function activeProductFilters()
    {
        return $this->belongsToMany(ProductFilter::class, 'qcommerce__active_product_filter')->orderBy('created_at')->withPivot(['use_for_variations']);
    }

    public function activeProductFiltersForVariations()
    {
        return $this->belongsToMany(ProductFilter::class, 'qcommerce__active_product_filter')->orderBy('created_at')->wherePivot('use_for_variations', 1)->withPivot(['use_for_variations']);
    }

    public function productCharacteristics()
    {
        return $this->hasMany(ProductCharacteristic::class);
    }

    public function productExtras()
    {
        return $this->hasMany(ProductExtra::class)->with(['ProductExtraOptions']);
    }

    public function allProductExtras()
    {
        return ProductExtra::where('product_id', $this->id)->orWhere('product_id', $this->parent_id)->with(['ProductExtraOptions'])->get();
    }

    public function bundleProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'qcommerce__product_bundle_products', 'product_id', 'bundle_product_id');
    }

    public function showableCharacteristics($withoutIds = [])
    {
        return Cache::tags(["product-$this->id"])->rememberForever("product-showable-characteristics-" . $this->id, function () use ($withoutIds) {
            $characteristics = [];

            $parentProduct = $this->parent;

            if ($parentProduct) {
                $activeFilters = $parentProduct->activeProductFilters;
            } else {
                $activeFilters = $this->activeProductFilters;
            }

            foreach ($activeFilters as $activeFilter) {
                $value = '';
                foreach ($activeFilter->productFilterOptions as $option) {
                    if ($this->productFilters()->where('product_filter_option_id', $option->id)->exists()) {
                        if ($value) {
                            $value .= ', ';
                        }
                        $value .= $option->name;
                    }
                }
                $characteristics[] = [
                    'name' => $activeFilter->name,
                    'value' => $value,
                ];
            }

            $allProductCharacteristics = ProductCharacteristics::orderBy('order')->get();
            foreach ($allProductCharacteristics as $productCharacteristic) {
                $thisProductCharacteristic = $this->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first();
                if ($thisProductCharacteristic && $thisProductCharacteristic->value && ! $productCharacteristic->hide_from_public && ! in_array($productCharacteristic->id, $withoutIds)) {
                    $characteristics[] = [
                        'name' => $productCharacteristic->name,
                        'value' => $thisProductCharacteristic->value,
                    ];
                }
            }

            return $characteristics;
        });
    }

    public function getCharacteristicById(int|array $id): array
    {
        if (is_array($id)) {
            $productCharacteristic = $this->productCharacteristics()->whereIn('product_characteristic_id', $id)->get();
            $productCharacteristics = [];
            foreach ($productCharacteristic as $pCharacteristic) {
                $productCharacteristics[] = [
                    'name' => $pCharacteristic->productCharacteristic->name,
                    'value' => $pCharacteristic->value,
                ];
            }

            return $productCharacteristics;
        } else {
            $productCharacteristic = $this->productCharacteristics->where('product_characteristic_id', $id)->first();
            if ($productCharacteristic) {
                return [
                    'name' => $productCharacteristic->productCharacteristic->name,
                    'value' => $productCharacteristic->value,
                ];
            }
        }

        return [];
    }

    public function getSuggestedProducts($limit = 4)
    {
        $suggestedProductIds = $this->suggestedProducts->pluck('id')->toArray();

        if (count($suggestedProductIds) < $limit) {
            $randomProductIds = Product::thisSite()->publicShowable()
                ->where('id', '!=', $this->id)
                ->whereNotIn('id', $suggestedProductIds)
                ->limit($limit - count($suggestedProductIds))
                ->inRandomOrder()
                ->pluck('id')->toArray();
            $suggestedProductIds = array_merge($randomProductIds, $suggestedProductIds);
        }

        return Product::thisSite()->publicShowable()->whereIn('id', $suggestedProductIds)->limit($limit)->inRandomOrder()->get();
    }

    public static function resolveRoute($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        if ($slugComponents[0] == Translation::get('products-slug', 'slug', 'products') && count($slugComponents) == 2) {
            $product = Product::thisSite()->where('slug->' . App::getLocale(), $slugComponents[1]);
            if (! auth()->check() || auth()->user()->role != 'admin') {
                $product->publicShowable(true);
            }
            $product = $product->first();

            if (! $product) {
                foreach (Product::thisSite()->publicShowable(true)->get() as $possibleProduct) {
                    if (! $product && $possibleProduct->slug == $slugComponents[1]) {
                        $product = $possibleProduct;
                    }
                }
            }

            if ($product) {
                if (View::exists('qcommerce.products.show')) {
                    seo()->metaData('metaTitle', $product->metadata && $product->metadata->title ? $product->metadata->title : $product->name);
                    seo()->metaData('metaDescription', $product->metadata->description ?? '');
                    $metaImage = $product->metadata->image ?? '';
                    if (! $metaImage) {
                        $metaImage = $product->firstImageUrl;
                    }
                    if ($metaImage) {
                        seo()->metaData('metaImage', $metaImage);
                    }

                    View::share('product', $product);

                    return view('qcommerce.products.show');
                } else {
                    return 'pageNotFound';
                }
            }
        }
    }
}
