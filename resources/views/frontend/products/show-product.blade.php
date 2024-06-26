<div>
    <x-blocks.breadcrumbs :breadcrumbs="$product->breadcrumbs()"/>
    <div class="mt-8">
        <x-container>
            <x-dashed-ecommerce-core::frontend.products.schema
                :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
            <div class="mx-auto max-w-2xl lg:max-w-none">
                <div class="lg:grid lg:grid-cols-2 lg:items-start lg:gap-x-8">
                    <div class="flex flex-col-reverse">
                        <div x-data="{
        imageGalleryOpened: false,
        imageGalleryActiveUrl: null,
        imageGalleryImageIndex: null,
        imageGallery: $wire.entangle('originalImages'),
        imageGalleryOpen(event) {
            this.imageGalleryImageIndex = event.target.dataset.index;
            this.imageGalleryActiveUrl = this.imageGallery[this.imageGalleryImageIndex - 1].image;
            this.imageGalleryOpened = true;
        },
        imageGalleryClose() {
            this.imageGalleryOpened = false;
            setTimeout(() => this.imageGalleryActiveUrl = null, 300);
        },
        imageGalleryNext(){
            this.imageGalleryImageIndex = (this.imageGalleryImageIndex == this.imageGallery.length) ? 1 : (parseInt(this.imageGalleryImageIndex) + 1);
            this.imageGalleryActiveUrl = this.imageGallery[this.imageGalleryImageIndex - 1].image;
        },
        imageGalleryPrev() {
            this.imageGalleryImageIndex = (this.imageGalleryImageIndex == 1) ? this.imageGallery.length : (parseInt(this.imageGalleryImageIndex) - 1);
            this.imageGalleryActiveUrl = this.imageGallery[this.imageGalleryImageIndex - 1].image;

        }
    }"
                             @image-gallery-next.window="imageGalleryNext()"
                             @image-gallery-prev.window="imageGalleryPrev()"
                             @keyup.right.window="imageGalleryNext();"
                             @keyup.left.window="imageGalleryPrev();"
                             class="w-full h-full select-none z-[100]">
                            <div class="products-splide splide"
                                 x-ref="splide"
                                 x-data="{
        init() {
            new Splide(this.$refs.splide, {
                perPage: 1,
                gap: '1rem',
            }).mount()
        },
    }">
                                <div class="splide__track">
                                    <ul
                                        class="splide__list">
                                        @foreach($images as $image)
                                            <li class="splide__slide">
                                                <img
                                                    class="object-contain object-center w-full"
                                                    x-on:click="imageGalleryOpen"
                                                    data-index="{{ $loop->iteration }}"
                                                    src="{{ app(\Dashed\Drift\UrlBuilder::class)->url('dashed', $image['image'], [
                                            'widen' => 800,
                                        ]) }}"
                                                    alt="{{ $image['alt_text'] }}"
                                                >
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <template x-teleport="body">
                                <div
                                    x-show="imageGalleryOpened"
                                    x-transition:enter="transition ease-in-out duration-300"
                                    x-transition:enter-start="opacity-0"
                                    x-transition:leave="transition ease-in-in duration-300"
                                    x-transition:leave-end="opacity-0"
                                    @click="imageGalleryClose"
                                    @keydown.window.escape="imageGalleryClose"
                                    x-trap.inert.noscroll="imageGalleryOpened"
                                    class="fixed inset-0 z-[99] flex items-center justify-center bg-primary bg-opacity-50 select-none cursor-zoom-out"
                                    x-cloak>
                                    <div class="relative flex items-center justify-center w-11/12 xl:w-4/5 h-11/12">
                                        @if(count($images) > 1)
                                            <div @click="$event.stopPropagation(); $dispatch('image-gallery-prev')"
                                                 class="absolute left-0 flex items-center justify-center text-white translate-x-10 rounded-full cursor-pointer xl:-translate-x-24 2xl:-translate-x-32 bg-primary/10 w-14 h-14 hover:bg-primary/20">
                                                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M15.75 19.5L8.25 12l7.5-7.5"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <img
                                            x-show="imageGalleryOpened"
                                            x-transition:enter="transition ease-in-out duration-300"
                                            x-transition:enter-start="opacity-0 transform scale-50"
                                            x-transition:leave="transition ease-in-in duration-300"
                                            x-transition:leave-end="opacity-0 transform scale-50"
                                            class="object-contain object-center w-full h-full select-none cursor-zoom-out"
                                            :src="imageGalleryActiveUrl" alt="" style="display: none;">
                                        @if(count($images) > 1)
                                            <div @click="$event.stopPropagation(); $dispatch('image-gallery-next');"
                                                 class="absolute right-0 flex items-center justify-center text-white -translate-x-10 rounded-full cursor-pointer xl:translate-x-24 2xl:translate-x-32 bg-primary/10 w-14 h-14 hover:bg-primary/20">
                                                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <div @click="imageGalleryClose"
                                             class="fixed right-6 top-6 flex items-center justify-center text-white rounded-full cursor-pointer bg-primary w-14 h-14 hover:bg-primary/80">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                 stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M6 18 18 6M6 6l12 12"/>
                                            </svg>

                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-10 px-4 sm:mt-16 sm:px-0 lg:mt-0">
                        <h2 class="text-3xl font-bold tracking-tight text-gray-900">{{ $name }}</h2>
                        @if($product->productCategories()->count())
                            <p class="mt-1 text-sm text-gray-500">{{$product->productCategories()->first()->name}}</p>
                        @endif

                        <div class="mt-3">
                            <h2 class="sr-only">{{ Translation::get('product-information', 'products', 'Product information') }}</h2>
                            <p class="text-3xl tracking-tight text-gray-900">{{ CurrencyHelper::formatPrice($price) }}
                                @if(Customsetting::get('taxes_prices_include_taxes'))
                                    {{ Translation::get('product-including-tax', 'products', 'incl. TAX') }}
                                @else
                                    {{ Translation::get('product-excluding-tax', 'products', 'excl. TAX') }}
                                @endif
                            </p>
                            @if($discountPrice)
                                <span
                                    class="text-sm line-through ml-2">{{CurrencyHelper::formatPrice($discountPrice)}}</span>
                            @endif
                        </div>

                        <div class="mt-6">
                            @if($product && $product->purchasable())
                                <p class="text-md tracking-wider text-primary-600 flex items-center font-bold"><span
                                        class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                          viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                    {{Translation::get('product-in-stock', 'product', 'In stock')}}
                                </p>
                            @else
                                <p class="text-md tracking-wider text-red-500 flex items-center font-bold"><span
                                        class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                          viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></span>{{Translation::get('product-out-of-stock', 'product', 'Out of stock')}}
                                </p>
                            @endif
                        </div>

                        @if($shortDescription)
                            <div class="mt-6">
                                <h3 class="sr-only">{{ Translation::get('product-description', 'products', 'Description') }}</h3>

                                <div class="space-y-6 text-base text-gray-700">
                                    <p>
                                        {{ $shortDescription }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="mt-6">
                            <livewire:cart.add-to-cart :product="$product"/>
                        </div>

                        @if($description)
                            <div class="mt-6">
                                <h3 class="sr-only">{{ Translation::get('product-description', 'products', 'Description') }}</h3>

                                <div class="space-y-6 text-base text-gray-700">
                                    {!! $description !!}
                                </div>
                            </div>
                        @endif

                        @if($characteristics)
                            <div class="mt-6 bg-gradient-to-tr from-primary-400 to-primary-600 text-white p-4">
                                <h3 class="sr-only">{{Translation::get('product-characteristics', 'product', 'Productkenmerken')}}</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($characteristics as $characteristic)
                                        <div class="font-bold">{{$characteristic['name']}}</div>
                                        <div>{{$characteristic['value']}}</div>
                                        @if(!$loop->last)
                                            <hr class="col-span-2">
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <section aria-labelledby="related-heading"
                         class="mt-10 border-t border-gray-200 px-4 py-16 sm:px-0">
                    <h2 id="related-heading"
                        class="text-xl font-bold text-gray-900">{{Translation::get('suggested-products', 'product', 'Also interesting')}}</h2>

                    <div class="mt-8 grid grid-cols-1 gap-y-12 sm:grid-cols-2 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                        @foreach($suggestedProducts as $suggestedProduct)
                            <x-product :product="$suggestedProduct"></x-product>
                        @endforeach
                    </div>
                </section>
            </div>
        </x-container>

        <x-blocks :content="$product->content"></x-blocks>
    </div>
    @script
    <script>
        $wire.on('productUpdated', () => {
            splide = new Splide('.products-splide').mount();
        });
    </script>
    @endscript
</div>
