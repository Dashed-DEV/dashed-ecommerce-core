<form wire:submit="addToCart">
    @if($filters)
        <div class="">
            @foreach($filters as $filterKey => $filter)
                @if(count($filter['options']))
                    <div class="grid">
                        <label for="filter-{{$filter['id']}}"
                               class="inline-block text-sm mb-2">
                            {{$filter['name']}}
                        </label>
                        <select
                            class="form-input"
                            id="filter-{{$filter['id']}}"
                            wire:model.live="filters.{{$filterKey}}.active">
                            <option
                                value="">{{ Translation::get('choose-a-option', 'product', 'Choose a option') }}</option>
                            @foreach($filter['options'] as $option)
                                <option value="{{ $option['id'] }}"
                                >
                                    {{$option['name']}}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
    @if($productExtras)
        <div class="">
            @foreach($productExtras as $extraKey => $extra)
                @if($extra->type == 'single')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="inline-block text-sm mb-2">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}
                        </label>
                        <select
                            class="form-input"
                            id="product-extra-{{$extra->id}}"
                            name="product-extra-{{$extra->id}}"
                            wire:model.live="extras.{{ $extraKey }}.value"
                            @if($extra->required) required @endif
                        >
                            <option value="">{{Translation::get('make-a-choice', 'product', 'Make a choice')}}</option>
                            @foreach($extra->productExtraOptions as $option)
                                <option
                                    value="{{$option->id}}">{{$option->value}} @if($option->price > 0)
                                        (+ {{CurrencyHelper::formatPrice($option->price)}})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @elseif($extra->type == 'multiple')
                    <p>Niet ondersteund</p>
                @elseif($extra->type == 'checkbox')
                    <div>
                        @foreach($extra->productExtraOptions as $option)
                            <label for="product-extra-{{$option->id}}"
                                   class="block text-sm font-medium text-primary mt-4">
                                {{$extra->name}}{{$extra->required ? '*' : ''}}:
                            </label>
                            <div class="relative flex items-start">
                                <div class="flex h-6 items-center">
                                    <input type="checkbox"
                                           class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                                           id="product-extra-{{$option->id}}"
                                           name="product-extra-{{$option->id}}"
                                           value="{{$option->id}}"
                                           wire:model.live="extras.{{ $extraKey }}.value">
                                </div>
                                <div class="ml-3 text-sm leading-6">
                                    <label for="product-extra-{{$option->id}}"
                                           class="font-medium text-gray-900">{{$option->value}} @if($option->price > 0)
                                            (+ {{CurrencyHelper::formatPrice($option->price)}})
                                        @endif</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif($extra->type == 'input')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="block text-sm font-medium text-primary mt-4">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}:
                        </label>
                        <div class="relative flex items-start">
                            <input type="{{ $extra->input_type }}"
                                   @if($extra->input_type == 'numeric')
                                       min="{{ $extra->min_length }}" max="{{ $extra->max_length }}"
                                   @else
                                       minlength="{{ $extra->min_length }}" maxlength="{{ $extra->max_length }}"
                                   @endif
                                   @if($extra->required) required @endif
                                   class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                                   id="product-extra-{{$extra->id}}"
                                   name="product-extra-{{$extra->id}}"
                                   wire:model.live.debounce.500ms="extras.{{ $extraKey }}.value">
                        </div>
                    </div>
                @elseif($extra->type == 'file')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="block text-sm font-medium text-primary mt-4">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}:
                        </label>
                        <div class="relative flex items-start">
                            <input type="file"
                                   @if($extra->required) required @endif
                                   class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                                   id="product-extra-{{$extra->id}}"
                                   name="product-extra-{{$extra->id}}"
                                   wire:model.live="files.{{ $extra->id }}.value">
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
    <div class="mt-4 grid gap-4">
        @if($product && $product->inStock())
            <button type="submit"
                    class="w-full button button--dark">{{Translation::get('add-to-cart', 'product', 'Add to cart')}}</button>
        @elseif(!$product)
            <div class="w-full button button--light pointer-events-none">
                {{Translation::get('choose-another-product', 'product', 'Choose another product')}}
            </div>
        @else
            <div class="w-full button button--light pointer-events-none">
                {{Translation::get('add-to-cart', 'product', 'Add to cart')}}
            </div>
        @endif
    </div>
</form>
