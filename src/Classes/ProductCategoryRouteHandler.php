<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ProductCategoryRouteHandler
{
    public static function handle($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        $productCategory = ProductCategory::where('slug->' . App::getLocale(), $slugComponents[0])->first();
        if ($productCategory) {
            array_shift($slugComponents);
            foreach ($slugComponents as $slugComponent) {
                if (! $productCategory) {
                    return 'pageNotFound';
                }
                $productCategory = ProductCategory::thisSite()->where('slug->' . App::getLocale(), $slugComponent)->where('parent_category_id', $productCategory->id)->first();
            }
            if (View::exists('qcommerce.categories.show') && $productCategory) {
                seo()->metaData('metaTitle', $productCategory->meta_title ?: $productCategory->name);
                seo()->metaData('metaDescription', $productCategory->meta_description);
                if ($productCategory->meta_image) {
                    seo()->metaData('metaImage', $productCategory->meta_image);
                }

                View::share('productCategory', $productCategory);

                $productsResponse = Products::getAll(request()->get('pagination') ?: 12, request()->get('order-by') ?: 'created_at', request()->get('order') ?: 'desc', $productCategory->id);
                View::share('products', $productsResponse['products']);
                View::share('filters', $productsResponse['filters']);

                return view('qcommerce.categories.show');
            } else {
                return 'pageNotFound';
            }
        }

        if ($slugComponents[0] == Translation::get('categories-slug', 'slug', 'categories')) {
            if (count($slugComponents) == 1) {
                if (View::exists('qcommerce.categories.index')) {
                    seo()->metaData('metaTitle', Translation::get('all-categories', 'categories', 'All categories'));

                    View::share('productCategory', null);
                    $childProductCategories = ProductCategories::getTopLevel(1000);
                    View::share('childProductCategories', $childProductCategories);

                    return view('qcommerce.categories.index');
                } else {
                    return 'pageNotFound';
                }
            } else {
                array_shift($slugComponents);
                $productCategory = ProductCategory::where('slug->' . App::getLocale(), $slugComponents[0])->first();
                if ($productCategory) {
                    array_shift($slugComponents);
                    foreach ($slugComponents as $slugComponent) {
                        if ($productCategory) {
                            $productCategory = ProductCategory::thisSite()->where('slug->' . App::getLocale(), $slugComponent)->where('parent_category_id', $productCategory->id)->first();
                        } else {
                            return 'pageNotFound';
                        }
                    }
                    if (View::exists('qcommerce.categories.index') && $productCategory) {
                        seo()->metaData('metaTitle', $productCategory->name);

                        View::share('productCategory', $productCategory);
                        $childProductCategories = $productCategory->getFirstChilds();
                        View::share('childProductCategories', $childProductCategories);

                        return view('qcommerce.categories.index');
                    } else {
                        return 'pageNotFound';
                    }
                }
            }
        }
    }
}
