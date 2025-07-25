<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{Translation::get('packing-slip', 'packing-slip', 'Packing slip')}} {{Customsetting::get('site_name')}}</title>
    <style>
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
            font-size: 16px;
            line-height: 24px;
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #555;
        }

        .invoice-box table {
            width: 100%;
            text-align: left;
        }

        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 40px;
        }

        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            height: 10px;
        }

        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }

        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td {
                width: 100%;
                display: block;
                text-align: center;
            }

            .invoice-box table tr.information table td {
                width: 100%;
                display: block;
                text-align: center;
            }
        }

        .logo-parent {
            text-align: left !important;
        }

        .logo {
            width: 125px !important;
            height: auto !important;
        }

        .left {
        }

        .h1-top {
            color: #33B679;
            display: inline-block;

            text-transform: uppercase;
            font-size: 20px;

            position: absolute;
            left: 200px;
            width: 125%;
        }

        .contact-table {
            position: relative;
            float: right;
            bottom: 25px;
        }

        .border-product {
            border: 1px solid black;
            padding: 20px;
        }

    </style>
</head>
<body>
<div class="invoice-box">
    <table cellpadding="0" cellspacing="0">
        <tr class="top">
            <td colspan="2">
                <table>
                    <tr>
                        <td class='logo-parent'>
                            @php($logo = Customsetting::get('site_logo', Sites::getActive(), ''))
                            @if($logo)
                                <img
                                    src="{{mediaHelper()->getSingleMedia($logo)->url ?? ''}}"
                                    class="logo">
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="">
                            @if($order->company_name)
                                {{$order->company_name}} <br>
                            @endif
                            {{ $order->name }}<br>
                            @if($order->btw_id)
                                {{$order->btw_id}} <br>
                            @endif
                            {{ $order->street }} {{ $order->house_nr }}<br>
                            {{ $order->zip_code }} {{ $order->city }}<br>
                            {{ $order->country }}
                        </td>
                        <td class="">
                            {{Customsetting::get('site_name')}}<br>
                            {{Customsetting::get('company_street')}} {{Customsetting::get('company_street_number')}}<br>
                            {{Customsetting::get('company_postal_code')}} {{Customsetting::get('company_city')}}<br>
                            {{Customsetting::get('company_country')}}
                            @if(Customsetting::get('company_kvk'))
                                <br>
                                {{Translation::get('kvk', 'invoice', 'KVK')}}: {{Customsetting::get('company_kvk')}}
                            @endif
                            @if(Customsetting::get('company_btw'))
                                <br>
                                {{Translation::get('btw', 'invoice', 'BTW')}}: {{Customsetting::get('company_btw')}}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td><b>{{Translation::get('package-slip', 'package-slip', 'Package slip')}}:</b><br><br>
            </td>
        </tr>
        @if($order->contains_pre_orders)
            <tr>
                <td colspan="2">
                    <div>
                        {{Translation::get('order-contains-pre-orders', 'orders', 'This order contains pre-orders')}}
                        <br>
                        <br>
                    </div>
                </td>
            </tr>
        @endif
        <tr>
            <div class='left' style="width:100%; display:block;margin-left:5px;">
                <div style="width:35%; display:inline-block; position:relative;top:0;">
                    <p>
                        {{Translation::get('package-slip-number', 'package-slip', 'Package slip number')}}: <br>
                        {{Translation::get('package-slip-date', 'package-slip', 'Package slip date')}}:<br>
                        {{Translation::get('package-slip-payment-method', 'package-slip', 'Payment method') . ':'}}<br>
                        {{Translation::get('package-slip-shipping-method', 'package-slip', 'Shipping method') . ':'}}
                    </p>
                </div>
                <div style="width:31%;display:inline-block; position:relative;top:0;">
                    <p>
                        <span>{{$order->invoice_id}}</span>
                        <br>
                        <span>{{$order->created_at->format('d-m-Y')}}</span><br>
                        <span>{{$order->paymentMethod}}</span><br>
                        <span>{{$order->shippingMethod->name ?? Translation::get('shipping-method-not-chosen', 'package-slip', 'niet gekozen')}}</span>
                        <br>
                    </p>
                </div>
            </div>
        </tr>
    </table>
    @if($order->note)
        <table>
            <tr>
                <td>
                    <b>{{Translation::get('note', 'package-slip', 'Note')}}:</b> {{ $order->note }}
                </td>
            </tr>
        </table>
    @endif
    <div>
        <table class="border-product">
            @foreach($order->orderProducts()->whereNotIn('sku', \Dashed\DashedEcommerceCore\Classes\SKUs::hideOnPackingSlip())->get() as $orderProduct)
                <tr>
                    <td>
                        {{$orderProduct->name}}
                        @if($orderProduct->product_extras)
                            @foreach($orderProduct->product_extras as $option)
                                <br>
                                <small>{{$option['name']}}: {{$option['value']}}</small>
                            @endforeach
                        @endif
                    </td>
                    <td>
                        {{$orderProduct->quantity}}x
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
    <div style="height:100px">
    </div>
    <div>
        <hr>
        <div style="width:30%;display:inline-block;">
            {{Customsetting::get('site_name')}}
        </div>
        <div
            style="float:right;display:inline-block;"><a
                href="mailto:{{Customsetting::get('site_to_email')}}">{{Customsetting::get('site_to_email')}}</a>
        </div>
    </div>
</div>
</body>
</html>
