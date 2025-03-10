<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Products;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Livewire\Concerns\ProductCartActions;

class ShowProduct extends Component
{
    use ProductCartActions;

    protected $listeners = [
        'setProductExtraCustomValue',
        'addToCart',
    ];

    public function mount($product = null, $productGroup = null)
    {
        $this->productGroup = $productGroup ?: $product->productGroup;
        $this->originalProduct = $product ?? null;
        $this->product = $product ?? null;

        $recentlyViewedProductGroups = session('recentlyViewedProducts', []);
        if (in_array($this->productGroup->id, $recentlyViewedProductGroups)) {
            $key = array_search($this->productGroup->id, $recentlyViewedProductGroups);
            unset($recentlyViewedProductGroups[$key]);
        }
        $recentlyViewedProductGroups[] = $this->productGroup->id;
        session(['recentlyViewedProducts' => $recentlyViewedProductGroups]);

        $metaModel = $this->product ?: $this->productGroup;

        seo()->metaData('metaTitle', $metaModel->metadata && $metaModel->metadata->title ? $metaModel->metadata->title : $metaModel->name);
        seo()->metaData('metaDescription', $metaModel->metadata->description ?? '');
        $metaImage = $metaModel->metadata->image ?? '';
        if (! $metaImage) {
            $metaImage = $metaModel->firstImage;
        }
        if ($metaImage) {
            seo()->metaData('metaImage', $metaImage);
        }

        $this->fillInformation(true);
    }

    public function updated()
    {
        $this->fillInformation();
    }

    public function rules()
    {
        return [
            'extras.*.value' => ['nullable'],
            'files.*' => ['nullable', 'file'],
        ];
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.products.show-product');
    }
}
