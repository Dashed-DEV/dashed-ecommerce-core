<?php

namespace Dashed\DashedEcommerceCore\Models;

use Exception;
use Carbon\Carbon;
use Filament\Forms\Get;
use Dashed\DashedPages\Models\Page;
use Illuminate\Support\Facades\App;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\View;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Gloudemans\Shoppingcart\CartItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Models\Concerns\IsVisitable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Dashed\LaravelLocalization\Facades\LaravelLocalization;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductUpdatedEvent;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProduct;

class Product extends Model
{
    use SoftDeletes;
    use HasDynamicRelation;
    use IsVisitable;
    use HasCustomBlocks;

    protected $table = 'dashed__products';

    public $translatable = [
        'name',
        'slug',
        'short_description',
        'description',
        'search_terms',
        'content',
        'images',
    ];

    public $resourceRelations = [
        'productExtras' => [
            'childRelations' => [
                'productExtraOptions',
            ],
        ],
    ];

    protected $with = [
//        'productFilters',
//        'parent',
//        'bundleProducts',
    ];

    protected $casts = [
        'site_ids' => 'array',
        'images' => 'array',
        'copyable_to_childs' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'expected_in_stock_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($product) {
            ProductCreatedEvent::dispatch($product);
        });

        static::updated(function ($product) {
            ProductUpdatedEvent::dispatch($product);
        });

        static::saved(function ($product) {
            if ($product->is_bundle && $product->type == 'variable' && ! $product->parent_id) {
                $product->is_bundle = false;
                $product->save();
                $product->bundleProducts()->detach();
            }

            ProductSavedEvent::dispatch($product);
            UpdateProductInformationJob::dispatch($product->productGroup);
        });

        static::deleting(function ($product) {
            $product->productCategories()->detach();
            $product->productFilters()->detach();
            $product->activeProductFilters()->detach();
            $product->shippingClass()->detach();
        });
    }

    public function productFilters()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__product_filter')
            ->orderBy('created_at')
            ->withPivot(['product_filter_option_id']);
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
                if (! method_exists($this, $attribute)) {
                    if ($loop == 1) {
                        $query->whereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
                    } else {
                        $query->orWhereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
                    }
                    $loop++;
                }
            }
            $query->orWhere('sku', $search)
                ->orWhere('ean', $search)
                ->orWhere('article_code', $search);
        });
    }

    public function scopePublic($query)
    {
        $query->where('public', 1);
    }

    public function scopeIndexable($query)
    {
        $query->where('indexable', 1);
    }

    public function scopeIsNotBundle($query)
    {
        $query->where('is_bundle', 0);
    }

    public function scopeIsBundle($query)
    {
        $query->where('is_bundle', 1);
    }

    public function scopePublicShowable($query, bool $overridePublic = false)
    {
        //        if (auth()->check() && auth()->user()->role == 'admin' && $overridePublic) {
        //            return;
        //        }

        $query
            ->public()
            ->thisSite()
            ->indexable();

        //        $query = $query->where(function ($query) {
        //            $query->where('start_date', null);
        //        })->orWhere(function ($query) {
        //            $query->where('start_date', '<=', Carbon::now());
        //        })->where(function ($query) {
        //            $query->where('end_date', null);
        //        })->orWhere(function ($query) {
        //            $query->where('end_date', '>=', Carbon::now());
        //        });

        return $query;
        //        }
    }

    public function scopeHandOrderShowable($query)
    {
        return;
        //        $query
        //            ->where(function ($query) {
        //                $query->where('type', '!=', 'variable')
        //                    ->where('sku', '!=', null)
        //                    ->where('price', '!=', null);
        //            })->orWhere(function ($query) {
        //                $query->where('type', 'variable')
        //                    ->where('parent_id', '!=', null)
        //                    ->where('sku', '!=', null)
        //                    ->where('price', '!=', null);
        //            });
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

        $homePage = Page::isHome()->publicShowable()->first();
        if ($homePage) {
            $breadcrumbs[] = [
                'name' => $homePage->name,
                'url' => $homePage->getUrl(),
            ];
        }

        return array_reverse($breadcrumbs);
    }

    public function getCurrentPriceAttribute()
    {
        return $this->getRawOriginal('current_price');
    }

    public function getDiscountPriceAttribute()
    {
        return $this->getRawOriginal('discount_price');
    }

    /**
     * @deprecated Use firstImage attribute instead.
     */
    public function getFirstImageUrlAttribute()
    {
        throw new Exception('This method is deprecated. Use the firstImage attribute instead.');
        //        return $this->images[0] ?? '';
    }

    public function getFirstImageAttribute()
    {
        return $this->images[0] ?? '';
    }

    /**
     * @deprecated You can now use the normal images array.
     */
    public function getAllImagesAttribute()
    {
        throw new Exception('This method is deprecated. Use the images attribute instead.');
        //        return $this->images ? collect($this->images) : collect();
    }

    /**
     * @deprecated Use the imagesExceptFirst attribute instead.
     */
    public function getAllImagesExceptFirstAttribute()
    {
        throw new Exception('This method is deprecated. Use the imagesExceptFirst attribute instead.');
        //        $images = $this->allImages;
        //        if (count($images)) {
        //            unset($images[0]);
        //        }
        //
        //        return $images;
    }

    public function getImagesExceptFirstAttribute(): array
    {
        $images = $this->images ?: [];
        if (count($images)) {
            unset($images[0]);
        }

        return $images;
    }

    public function getUrl($locale = null, $forceOwnUrl = false)
    {
        if (! $locale) {
            $locale = app()->getLocale();
        }

        return Cache::remember('product-' . $this->id . '-url-' . $locale . '-force-' . $forceOwnUrl, 60 * 5, function () use ($locale, $forceOwnUrl) {
            if ($this->productGroup->only_show_parent_product && ! $forceOwnUrl) {
                return $this->productGroup->getUrl();
            } else {
                $url = '/' . Translation::get('products-slug', 'slug', 'products') . '/' . $this->slug;
            }

            if ($locale != config('app.locale')) {
                $url = App::getLocale() . '/' . $url;
            }

            return LaravelLocalization::localizeUrl($url);
        });
    }

    public function getStatusAttribute()
    {
        if (! $this->public) {
            return false;
        } else {
            return true;
        }
    }

    //    //Only used for old method/style
    //    public function filters()
    //    {
    //        $parentProduct = $this->parent;
    //
    //        if ($parentProduct) {
    //            $childProducts = $parentProduct->childProducts()->publicShowable()->get();
    //            $activeFilters = $parentProduct->activeProductFiltersForVariations;
    //        } else {
    //            $childProducts = [
    //                $this,
    //            ];
    //            $activeFilters = $this->activeProductFiltersForVariations;
    //        }
    //
    //        $showableFilters = [];
    //        $activeFiltersValues = [];
    //
    //        foreach ($activeFilters as $activeFilter) {
    //            $filterOptionValues = [];
    //            foreach ($childProducts as $childProduct) {
    //                $filterName = '';
    //                $activeFilterId = '';
    //                $activeFilterOptionIds = [];
    //                $activeFilterOptions = [];
    //
    //                foreach ($activeFilter->productFilterOptions as $option) {
    //                    if ($childProduct->productFilters()->where('product_filter_option_id', $option->id)->exists()) {
    //                        if ($filterName) {
    //                            $filterName .= ', ';
    //                            $activeFilterId .= '-';
    //                        }
    //                        $filterName .= $option->name;
    //                        $activeFilterId .= $option->id;
    //                        $activeFilterOptionIds[] = $option->id;
    //                        $activeFilterOptions[] = $option;
    //                    }
    //                }
    //
    //                //If something does not work correct, check if below code makes sure there is a active one
    //                //Array key must be string, otherwise Livewire renders it in order of id, instead of order from filter option
    //                if (count($activeFilterOptionIds) && (!array_key_exists('filter-' . $activeFilterId, $filterOptionValues) || $this->id == $childProduct->id)) {
    //                    $filterOptionValues['filter-' . $activeFilterId] = [
    //                        'id' => $activeFilter->id,
    //                        'name' => $filterName,
    //                        'order' => $activeFilterOptions[0]->order,
    //                        'activeFilterOptionIds' => $activeFilterOptionIds,
    //                        'value' => implode('-', $activeFilterOptionIds),
    //                        'active' => $this->id == $childProduct->id,
    //                        'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
    //                        'productId' => ($this->id == $childProduct->id) ? $this->id : '',
    //                        'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
    //                        'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
    //                        'isPreOrder' => ($this->id == $childProduct->id) ? $this->isPreorderable() : false,
    //                    ];
    //                    if ($this->id == $childProduct->id) {
    //                        $activeFiltersValues['filter-' . $activeFilterId] = [
    //                            'id' => $activeFilter->id,
    //                            'name' => $filterName,
    //                            'activeFilterOptionIds' => $activeFilterOptionIds,
    //                            'value' => implode('-', $activeFilterOptionIds),
    //                            'active' => $this->id == $childProduct->id,
    //                            'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
    //                            'productId' => ($this->id == $childProduct->id) ? $this->id : '',
    //                            'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
    //                            'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
    //                            'isPreOrder' => ($this->id == $childProduct->id) ? ($this->isPreorderable()) : false,
    //                        ];
    //
    //                        $activeFilterValue = implode('-', $activeFilterOptionIds);
    //                    }
    //                }
    //            }
    //
    //            $showableFilters[] = [
    //                'id' => $activeFilter->id,
    //                'name' => $activeFilter->name,
    //                'active' => $activeFilterValue ?? null,
    //                'defaultActive' => $activeFilterValue ?? null,
    //                'values' => $filterOptionValues,
    //                'contentBlocks' => $activeFilter->contentBlocks,
    //            ];
    //        }
    //
    //        foreach ($showableFilters as &$showableFilter) {
    //            $correctFilterOptions = 0;
    //            foreach ($showableFilter['values'] as &$showableFilterValue) {
    //                if (!$showableFilterValue['url']) {
    //                    foreach ($childProducts as $childProduct) {
    //                        if ($childProduct->id != $this->id) {
    //                            $productIsCorrectForFilter = true;
    //                            foreach ($showableFilterValue['activeFilterOptionIds'] as $activeFilterOptionId) {
    //                                if (!$childProduct->productFilters()->where('product_filter_option_id', $activeFilterOptionId)->exists()) {
    //                                    $productIsCorrectForFilter = false;
    //                                }
    //                            }
    //                            if ($productIsCorrectForFilter) {
    //                                foreach ($activeFiltersValues as $activeFilterValue) {
    //                                    if ($activeFilterValue['id'] != $showableFilterValue['id']) {
    //                                        $productHasCorrectFilterOption = true;
    //                                        foreach ($activeFilterValue['activeFilterOptionIds'] as $activeFilterOptionId) {
    //                                            if (!$childProduct->productFilters()->where('product_filter_option_id', $activeFilterOptionId)->exists()) {
    //                                                $productHasCorrectFilterOption = false;
    //                                            }
    //                                        }
    //                                        if (!$productHasCorrectFilterOption) {
    //                                            $productIsCorrectForFilter = false;
    //                                        }
    //                                    }
    //                                }
    //                            }
    //                            if ($productIsCorrectForFilter) {
    //                                $showableFilterValue['url'] = $childProduct->getUrl();
    //                                $showableFilterValue['productId'] = $childProduct->id;
    //                                $showableFilterValue['in_stock'] = $childProduct->inStock();
    //                                $showableFilterValue['inStock'] = $childProduct->inStock();
    //                                $showableFilterValue['isPreOrder'] = $childProduct->isPreorderable();
    //                                $correctFilterOptions++;
    //                            }
    //                        }
    //                    }
    //                } else {
    //                    $correctFilterOptions++;
    //                }
    //            }
    //            $showableFilter['correctFilterOptions'] = $correctFilterOptions;
    //        }
    //
    //        foreach ($showableFilters as &$showableFilter) {
    //            $showableFilter['values'] = collect($showableFilter['values'])->sortBy('order');
    //        }
    //
    //        return $showableFilters;
    //    }

    public function simpleFilters(): array
    {
        $filters = [];

        foreach ($this->activeProductFilters as $filter) {
            if ($filter->pivot->use_for_variations) {
                $filterOptions = $filter->productFilterOptions()->whereIn('id', $this->enabledProductFilterOptions()->pluck('product_filter_option_id'))->get()->toArray();

                if (count($filterOptions)) {
                    foreach ($filterOptions as &$filterOption) {
                        $filterOption['name'] = $filterOption['name'][App::getLocale()] ?? $filterOption['name'][0];
                    }

                    $filters[] = [
                        'id' => $filter->id,
                        'name' => $filter['name'],
                        'options' => $filterOptions,
                        'type' => $filter->type,
                        'active' => null,
                    ];
                }
            }
        }

        return $filters;
    }

    public function reservedStock()
    {
        return OrderProduct::where('product_id', $this->id)->whereIn('order_id', Order::whereIn('status', ['pending'])->pluck('id'))->count();
    }

    public function stock()
    {
        return $this->total_stock;
    }

    public function calculateStock()
    {
        $stock = 0;

        if ($this->is_bundle) {
            $minStock = 100000;
            foreach ($this->bundleProducts as $bundleProduct) {
                if ($bundleProduct->stock() < $minStock) {
                    $minStock = $bundleProduct->stock();
                }
            }

            $stock = $minStock;
        } elseif ($this->use_stock) {
            if ($this->outOfStockSellable()) {
                $stock = 100000;
            } else {
                $stock = $this->stock - $this->reservedStock();
            }
        } else {
            if ($this->stock_status == 'in_stock') {
                $stock = 100000;
            } else {
                $stock = 0;
            }
        }

        $this->total_stock = $stock;
        $this->saveQuietly();
        $this->calculateInStock();
    }

    public function calculatePrices()
    {
        if ($this->is_bundle && $this->use_bundle_product_price) {
            $currentPrice = $this->bundleProducts()->sum('price');
        } else {
            $currentPrice = $this->price;
        }
        $this->current_price = $currentPrice;

        if ($this->is_bundle && $this->use_bundle_product_price) {
            $discountPrice = $this->bundleProducts()->sum('new_price');
        } else {
            if ($this->new_price) {
                $discountPrice = $this->new_price;
            } else {
                $discountPrice = null;
            }
        }

        $this->discount_price = $discountPrice;
        $this->saveQuietly();
    }

    public function hasDirectSellableStock(): bool
    {
        if ($this->is_bundle) {
            $allBundleProductsDirectSellable = true;

            foreach ($this->bundleProducts as $bundleProduct) {
                if (! $bundleProduct->hasDirectSellableStock()) {
                    $allBundleProductsDirectSellable = false;
                }
            }

            if ($allBundleProductsDirectSellable) {
                return true;
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

    public function inStock(): bool
    {
        return $this->in_stock;
    }

    public function calculateInStock(): void
    {
        $inStock = false;

        if ($this->is_bundle) {
            $allBundleProductsInStock = true;

            foreach ($this->bundleProducts as $bundleProduct) {
                if (! $bundleProduct->inStock()) {
                    $allBundleProductsInStock = false;
                }
            }

            $inStock = $allBundleProductsInStock;
        } else {
            $inStock = $this->stock() > 0;
        }

        $this->in_stock = $inStock;
        $this->saveQuietly();
    }

    public function calculateTotalPurchases(): void
    {
        $purchases = $this->purchases;

        $this->total_purchases = $purchases;
        $this->saveQuietly();
    }

    public function calculateDeliveryTime(): void
    {
        if ($this->is_bundle) {
            $deliveryDays = 0;
            $deliveryDate = null;

            foreach ($this->bundleProducts as $bundleProduct) {
                if ($bundleProduct->inStock() && ! $bundleProduct->hasDirectSellableStock()) {
                    if ($bundleProduct->expectedDeliveryInDays() > $deliveryDays) {
                        $deliveryDays = $bundleProduct->expectedDeliveryInDays();
                    }
                    if ($bundleProduct->expectedInStockDate() && (! $deliveryDate || $bundleProduct->expectedInStockDate() > $deliveryDate)) {
                        $deliveryDate = $bundleProduct->expectedInStockDate();
                    }
                }
            }

            if ($deliveryDays && $deliveryDate && $deliveryDate <= now()->addDays($deliveryDays)) {
                $this->expected_delivery_in_days = $deliveryDays;
                $this->expected_in_stock_date = null;
            } elseif ($deliveryDays && $deliveryDate && $deliveryDate > now()->addDays($deliveryDays)) {
                $this->expected_in_stock_date = $deliveryDate;
                $this->expected_delivery_in_days = null;
            } elseif ($deliveryDays) {
                $this->expected_delivery_in_days = $deliveryDays;
                $this->expected_in_stock_date = null;
            } elseif ($deliveryDate) {
                $this->expected_in_stock_date = $deliveryDate;
                $this->expected_delivery_in_days = null;
            } else {
                $this->expected_in_stock_date = null;
                $this->expected_delivery_in_days = null;
            }
            $this->saveQuietly();
        }
    }

    public function outOfStockSellable(): bool
    {
        if (! $this->use_stock) {
            if ($this->stock_status == 'out_of_stock') {
                return false;
            }
        }

        if (! $this->out_of_stock_sellable) {
            return false;
        }

        if ((Customsetting::get('product_out_of_stock_sellable_date_should_be_valid', Sites::getActive(), 1) && ! $this->expectedInStockDateValid()) && ! $this->expectedDeliveryInDays()) {
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

    public function expectedInStockDateInWeeks(): float
    {
        $expectedInStockDate = self::expectedInStockDate();
        if (! $expectedInStockDate || Carbon::parse($expectedInStockDate) < now()) {
            return 0;
        }

        $diffInWeeks = floor(now()->subDay()->diffInWeeks(Carbon::parse($expectedInStockDate)));
        if ($diffInWeeks < 0) {
            $diffInWeeks = 0;
        }

        return $diffInWeeks;
    }

    public function expectedDeliveryInDays(): int
    {
        $expectedDeliveryInDays = $this->expected_delivery_in_days ?: 0;

        return $expectedDeliveryInDays;
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
        return $this->belongsTo(ProductGroup::class, 'product_group_id')
            ->withTrashed();
    }

    public function productCategories()
    {
        return $this->belongsToMany(ProductCategory::class, 'dashed__product_category');
    }

    public function suggestedProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_suggested_product', 'product_id', 'suggested_product_id');
    }

    public function crossSellProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_crosssell_product', 'product_id', 'crosssell_product_id');
    }

    public function shippingClasses()
    {
        return $this->belongsToMany(ShippingClass::class, 'dashed__product_shipping_class');
    }

    public function tabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_id', 'tab_id')
            ->orderBy('order');
    }

    public function globalTabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_id', 'tab_id')
            ->where('global', 1);
    }

    public function ownTabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_id', 'tab_id')
            ->where('global', 0);
    }

    public function productCharacteristics()
    {
        return $this->hasMany(ProductCharacteristic::class);
    }

    public function allProductExtras(): ?Collection
    {
        $productExtraIds = [];

        $productExtraIds = array_merge($productExtraIds, $this->productExtras->pluck('id')->toArray());
        $productExtraIds = array_merge($productExtraIds, $this->globalProductExtras->pluck('id')->toArray());

        if ($this->parent) {
            $productExtraIds = array_merge($productExtraIds, $this->parent->productExtras->pluck('id')->toArray());
            $productExtraIds = array_merge($productExtraIds, $this->parent->globalProductExtras->pluck('id')->toArray());
        }

        return ProductExtra::whereIn('id', $productExtraIds)
            ->with(['ProductExtraOptions'])
            ->get();
    }

    public function productExtras(): HasMany
    {
        return $this->hasMany(ProductExtra::class)
            ->with(['productExtraOptions']);
    }

    public function globalProductExtras(): BelongsToMany
    {
        return $this->belongsToMany(ProductExtra::class, 'dashed__product_extra_product', 'product_id', 'product_extra_id')
            ->where('global', 1)
            ->with(['productExtraOptions']);
    }

    public function bundleProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__product_bundle_products', 'product_id', 'bundle_product_id');
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function showableCharacteristics($withoutIds = [])
    {
        return Cache::rememberForever("product-showable-characteristics-" . $this->id, function () use ($withoutIds) {
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

    public function getSuggestedProducts(int $limit = 4, bool $random = true): Collection
    {
        $suggestedProductIds = $this->suggestedProducts->pluck('id')->toArray();

        if (count($suggestedProductIds) < $limit) {
            $randomProductIds = Product::thisSite()
                ->publicShowable()
                ->where('id', '!=', $this->id)
                ->whereNotIn('id', array_merge($suggestedProductIds, [$this->id]))
                ->limit($limit - count($suggestedProductIds))
                ->inRandomOrder()
                ->pluck('id')
                ->toArray();
            $suggestedProductIds = array_merge($randomProductIds, $suggestedProductIds);
        }

        $products = Product::thisSite()->publicShowable()->whereIn('id', $suggestedProductIds)->limit($limit);
        if ($random) {
            $products->inRandomOrder();
        }

        return $products->get();
    }

    public function getCrossSellProducts(): Collection
    {
        $crossSellProductsIds = $this->crossSellProducts->pluck('id')->toArray();

        //        if (count($crossSellProductsIds) < $limit) {
        //            $randomProductIds = Product::thisSite()
        //                ->publicShowable()
        //                ->where('id', '!=', $this->id)
        //                ->whereNotIn('id', $crossSellProductsIds)
        //                ->limit($limit - count($crossSellProductsIds))
        //                ->inRandomOrder()
        //                ->pluck('id')
        //                ->toArray();
        //            $crossSellProductsIds = array_merge($randomProductIds, $crossSellProductsIds);
        //        }

        $products = Product::thisSite()->publicShowable()->whereIn('id', $crossSellProductsIds);

        //            ->limit($limit);
        //        if ($random) {
        //            $products->inRandomOrder();
        //        }
        //
        return $products->get();
    }

    public static function getShoppingCartItemPrice(CartItem $cartItem, string|DiscountCode|null $discountCode = null)
    {
        if ($discountCode && is_string($discountCode)) {
            $discountCode = null;
        }

        $quantity = $cartItem->qty;
        $options = $cartItem->options['options'];

        $price = 0;

        $price += ($cartItem->model ? $cartItem->model->currentPrice : $cartItem->options['singlePrice']) * $quantity;

        foreach ($options as $productExtraOptionId => $productExtraOption) {
            if (! str($productExtraOptionId)->contains('product-extra-')) {
                $thisProductExtraOption = ProductExtraOption::find($productExtraOptionId);
                if ($thisProductExtraOption) {
                    if ($thisProductExtraOption->calculate_only_1_quantity) {
                        $price += $thisProductExtraOption->price;
                    } else {
                        $price += ($thisProductExtraOption->price * $quantity);
                    }
                }
            }
        }

        if ($discountCode && $discountCode->type == 'percentage') {
            $discountValidForProduct = false;

            if ($discountCode->valid_for == 'categories') {
                if ($cartItem->model && $discountCode->productCategories()->whereIn('product_category_id', $cartItem->model->productCategories()->pluck('product_category_id'))->exists()) {
                    $discountValidForProduct = true;
                }
            } elseif ($discountCode->valid_for == 'products') {
                if ($cartItem->model && $discountCode->products()->where('product_id', $cartItem->model->id)->exists()) {
                    $discountValidForProduct = true;
                }
            } else {
                $discountValidForProduct = true;
            }

            if ($discountValidForProduct) {
                $price = round(($price / $quantity / 100) * (100 - $discountCode->discount_percentage), 2) * $quantity;
            }

            if ($price < 0) {
                $price = 0.01;
            }
        }

        return $price;
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
                seo()->metaData('metaTitle', $product->metadata && $product->metadata->title ? $product->metadata->title : $product->name);
                seo()->metaData('metaDescription', $product->metadata->description ?? '');
                $metaImage = $product->metadata->image ?? '';
                if (! $metaImage) {
                    $metaImage = $product->firstImage;
                }
                if ($metaImage) {
                    seo()->metaData('metaImage', $metaImage);
                }

                View::share('model', $product);
                View::share('product', $product);

                return [
                    'livewireComponent' => ShowProduct::class,
                    'parameters' => [
                        'product' => $product,
                    ],
                ];
                //                if (View::exists(env('SITE_THEME', 'dashed') . '.products.show')) {
                //                    seo()->metaData('metaTitle', $product->metadata && $product->metadata->title ? $product->metadata->title : $product->name);
                //                    seo()->metaData('metaDescription', $product->metadata->description ?? '');
                //                    $metaImage = $product->metadata->image ?? '';
                //                    if (! $metaImage) {
                //                        $metaImage = $product->firstImage;
                //                    }
                //                    if ($metaImage) {
                //                        seo()->metaData('metaImage', $metaImage);
                //                    }
                //
                //                    View::share('model', $product);
                //                    View::share('product', $product);
                //
                //                    return view(env('SITE_THEME', 'dashed') . '.products.show');
                //                } else {
                //                    return 'pageNotFound';
                //                }
            }
        }
    }

    public static function stockFilamentSchema(): array
    {
        return [
            Toggle::make('use_stock')
                ->label('Voorraad bijhouden')
                ->reactive(),
            Toggle::make('limit_purchases_per_customer')
                ->label('Dit product mag maar een x aantal keer per bestelling gekocht worden')
                ->reactive(),
            Toggle::make('out_of_stock_sellable')
                ->label('Product doorverkopen wanneer niet meer op voorraad (pre-orders)')
                ->reactive()
                ->hidden(fn (Get $get) => ! $get('use_stock')),
            Toggle::make('low_stock_notification')
                ->label('Ik wil een melding krijgen als dit product laag op voorraad raakt')
                ->reactive()
                ->hidden(fn (Get $get) => ! $get('use_stock')),
            TextInput::make('stock')
                ->type('number')
                ->label('Hoeveel heb je van dit product op voorraad')
                ->helperText(fn ($record) => $record ? 'Er zijn er momenteel ' . $record->reservedStock() . ' gereserveerd' : '')
                ->maxValue(100000)
                ->required()
                ->numeric()
                ->hidden(fn (Get $get) => ! $get('use_stock')),
            DatePicker::make('expected_in_stock_date')
                ->label('Wanneer komt dit product weer op voorraad')
                ->reactive()
                ->helperText('Gebruik 1 van deze 2 opties')
                ->required(fn (Get $get) => ! $get('expected_delivery_in_days'))
                ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
            TextInput::make('expected_delivery_in_days')
                ->label('Levering in dagen')
                ->helperText('Hoeveel dagen duurt het voordat dit product geleverd kan worden?')
                ->reactive()
                ->numeric()
                ->minValue(1)
                ->maxValue(1000)
                ->required(fn (Get $get) => ! $get('expected_in_stock_date') && $get('out_of_stock_sellable')),
            TextInput::make('low_stock_notification_limit')
                ->label('Lage voorraad melding')
                ->helperText('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een melding')
                ->type('number')
                ->reactive()
                ->required()
                ->minValue(1)
                ->maxValue(100000)
                ->default(1)
                ->numeric()
                ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('low_stock_notification')),
            Select::make('stock_status')
                ->label('Is dit product op voorraad')
                ->options([
                    'in_stock' => 'Op voorraad',
                    'out_of_stock' => 'Uitverkocht',
                ])
                ->default('in_stock')
                ->required()
                ->hidden(fn (Get $get) => $get('use_stock')),
            TextInput::make('limit_purchases_per_customer_limit')
                ->type('number')
                ->label('Hoeveel mag dit product gekocht worden per bestelling')
                ->minValue(1)
                ->maxValue(100000)
                ->default(1)
                ->required()
                ->numeric()
                ->hidden(fn (Get $get) => ! $get('limit_purchases_per_customer')),
            Select::make('fulfillment_provider')
                ->label('Door wie wordt dit product verstuurd?')
                ->helperText('Laat leeg voor eigen fulfillment')
                ->options(function () {
                    $options = [];

                    foreach (ecommerce()->builder('fulfillmentProviders') as $key => $provider) {
                        $options[$key] = $provider['name'];
                    }

                    return $options;
                }),
        ];
    }
}
