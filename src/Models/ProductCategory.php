<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\HasMedia;
use Qubiqx\Qcommerce\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class ProductCategory extends Model implements HasMedia
{
    use SoftDeletes;
    use HasTranslations;
    use InteractsWithMedia;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'qcommerce__product_categories';

    protected $fillable = [
        'parent_category_id',
        'site_ids',
        'name',
        'slug',
        'content',
        'meta_title',
        'meta_description',
    ];

    public $translatable = [
        'name',
        'slug',
        'content',
        'meta_title',
        'meta_description',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $with = [
        'parentProductCategory',
        'media',
    ];

    protected static function booted()
    {
        static::created(function ($productCategory) {
            Cache::tags(['product-categories'])->flush();
        });

        static::updated(function ($productCategory) {
            Cache::tags(['product-categories'])->flush();
        });
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this
            ->addMediaConversion('preview')
            ->fit(Manipulations::FIT_CROP, 300, 300)
            ->nonQueued();
    }

    public function scopeSearch($query)
    {
        if (request()->get('search')) {
            $search = strtolower(request()->get('search'));
            $query->where('name', 'LIKE', "%$search%")
                ->orWhere('slug', 'LIKE', "%$search%")
                ->orWhere('content', 'LIKE', "%$search%")
                ->orWhere('meta_title', 'LIKE', "%$search%")
                ->orWhere('meta_description', 'LIKE', "%$search%")
                ->orWhere('site_ids', 'LIKE', "%$search%");
        }
    }

    public function scopeThisSite($query)
    {
        $query->where('site_ids->' . config('qcommerce.currentSite'), 'active');
    }

    public function getImageAttribute()
    {
        $image = $this->getFirstMedia('image-' . app()->getLocale());
        if ($image) {
            return $image;
        }
    }

    public function parentProductCategory()
    {
        return $this->belongsTo(self::class, 'parent_category_id');
    }

    public function getProductsUrl()
    {
        $url = $this->slug;
        $parentCategory = $this->parentProductCategory;
        while ($parentCategory) {
            $url = $parentCategory->slug . '/' . $url;
            $parentCategory = $parentCategory->parentProductCategory;
        }

        return url($url);
    }

    public function getUrl()
    {
        if (! $this->hasChilds()) {
            if ($this->products->count() == 1) {
                return $this->products->first()->getUrl();
            } else {
                return $this->getProductsUrl();
            }
        }

        $url = $this->slug;
        $parentCategory = $this->parentProductCategory;
        while ($parentCategory) {
            $url = $parentCategory->slug . '/' . $url;
            $parentCategory = $parentCategory->parentProductCategory;
        }

        $url = Translation::get('categories-slug', 'slug', 'categories') . '/' . $url;

        return LaravelLocalization::localizeUrl($url);
    }

    public function activeSiteIds()
    {
        $category = $this;
        while ($category->parent_category_id) {
            $category = self::find($category->parent_category_id);
            if (! $category) {
                return;
            }
        }

        $sites = [];
        foreach (Sites::getSites() as $site) {
            if (self::where('id', $category->id)->where('site_ids->' . $site['id'], 'active')->count()) {
                array_push($sites, $site['id']);
            }
        }

        return $sites;
    }

    public function siteNames()
    {
        $category = $this;
        while ($category->parent_category_id) {
            $category = self::find($category->parent_category_id);
            if (! $category) {
                return;
            }
        }

        $sites = [];
        foreach (Sites::getSites() as $site) {
            if (self::where('id', $category->id)->where('site_ids->' . $site['id'], 'active')->count()) {
                $sites[$site['name']] = 'active';
            } else {
                $sites[$site['name']] = 'inactive';
            }
        }

        return $sites;
    }

    public function hasChilds()
    {
        return (bool)$this->getFirstChilds()->count();
//        return self::where('parent_category_id', $this->id)->count() ? true : false;
    }

    public function getChilds()
    {
        $childs = [];
        $childProductCategories = self::where('parent_category_id', $this->id)->get();
        while ($childProductCategories->count()) {
            $childProductCategoryIds = [];
            foreach ($childProductCategories as $childProductCategory) {
                $childProductCategoryIds[] = $childProductCategory->id;
                $childs[] = $childProductCategory;
            }
            $childProductCategories = self::with(['products'])->whereIn('parent_category_id', $childProductCategoryIds)->get();
        }

        return $childs;
    }

    public function getFirstChilds()
    {
        return Cache::tags(['product-categories', 'products'])->rememberForever("product-category-childs-$this->id", function () {
            return self::with(['products'])->where('parent_category_id', $this->id)->get();
        });
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'qcommerce__product_category');
    }
}