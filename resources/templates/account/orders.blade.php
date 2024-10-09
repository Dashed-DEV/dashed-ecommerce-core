<x-container>
    <div class="grid grid-cols-6 gap-8 py-16 sm:py-24">
        <div class="col-span-4 lg:col-span-2">
            <nav class="grid space-y-2" aria-label="Tabs">
                <a href="{{AccountHelper::getAccountUrl()}}"
                   class="button button--primary-dark">
                    {{Translation::get('my-account', 'account', 'Mijn account')}}
                </a>
                <a href="{{EcommerceAccountHelper::getAccountOrdersUrl()}}"
                   class="button button--primary-light">
                    {{Translation::get('my-orders', 'account', 'Mijn bestellingen')}}
                </a>
                <a href="{{AccountHelper::getLogoutUrl()}}"
                   class="button button--primary-dark">
                    {{Translation::get('logout', 'login', 'Uitloggen')}}
                </a>
            </nav>
        </div>
        <div class="col-span-6 lg:col-span-4">
            <h1 class="text-2xl">{{Translation::get('my-orders', 'account', 'Mijn bestellingen')}}</h1>
            @if(!$orders->count())
                <p>{{Translation::get('no-orders-yet', 'account', 'Je hebt nog geen bestellingen')}}</p>
            @else
                <div class="mt-8 space-y-8">
                    @foreach($orders as $order)
                        <div class="space-y-6 shadow-xl text-white rounded-md bg-gradient-to-tr from-primary-200/50 to-primary-300 p-4">
                            <div class="md:flex items-center justify-between">
                                <p>{{$order->created_at->format('d-m-Y')}} | {{$order->invoice_id}}</p>
                                <div class="space-y-2">
                                    <a class="button button--primary-dark"
                                       href="{{$order->downloadInvoiceUrl()}}">
                                        {{Translation::get('download-invoice', 'cart', 'Download factuur')}}
                                    </a>
                                    <a class="button button--primary-dark"
                                       href="{{$order->getUrl()}}">
                                        {{Translation::get('view-order', 'cart', 'Bekijk bestelling')}}
                                    </a>
                                </div>
                            </div>

                            <div class="grid gap-8 grid-cols-1 sm:grid-cols-2">
                                @foreach($order->orderProducts as $orderProduct)
                                    <a href="{{$orderProduct->product ? $orderProduct->product->getUrl() : '#'}}"
                                       class="flex py-4 space-x-4">
                                        @if($orderProduct->product && $orderProduct->product->firstImage)
                                            <x-drift::image
                                                class="object-cover h-16"
                                                config="dashed"
                                                :path="$orderProduct->product->firstImage"
                                                :alt=" $orderProduct->product->name"
                                                :manipulations="[
                                                    'widen' => 150,
                                                ]"
                                            />
                                        @endif

                                        <article>
                                            <h3 class="text-sm md:text-2xl font-bold line-clamp-5">{{$orderProduct->name}}
                                                @if($orderProduct->product_extras)
                                                    <div>
                                                        @foreach($orderProduct->product_extras as $option)
                                                            <p class="text-xs">{{$option['name']}}
                                                                : {{$option['value']}}</p>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </h3>
                                            <p class="text-lg font-bold">
                                                {{CurrencyHelper::formatPrice($orderProduct->price)}}
                                                <span>|</span>
                                                {{$orderProduct->quantity}}
                                                <span>x</span>
                                            </p>
                                        </article>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-container>