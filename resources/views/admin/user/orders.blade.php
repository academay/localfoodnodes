@extends('admin.layout')

@section('title', join(array_keys($breadcrumbs), ' - '))

@section('content')
    @include('admin.page-header')

    <div class="row">
        <div class="col-12">
            @if ($user->orderItemsGroupedByProducer()->count() > 0)
                @foreach ($user->orderItemsGroupedByProducer() as $producerId => $orderItems)
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <span>{{ $orderItems->first()->getProducer()['name'] }}</span> - <span>{{ $orderItems->first()->getProducer()['email'] }}</span>
                        </div>
                        <div class="card-block">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ trans('admin/user.product') }}</th>
                                        <th class="text-right">{{ trans('admin/user.quantity') }}</th>
                                        <th>{{ trans('admin/user.node') }}</th>
                                        <th>{{ trans('admin/user.date') }}</th>
                                        <th class="text-right">{{ trans('admin/user.total') }}</th>
                                    </tr>
                                </thead>
                                @foreach ($orderItems as $orderItem)
                                    <tr>
                                        <td><a href="/account/user/order/{{ $orderItem->orderDateItemLink()->id }}">{{ $orderItem->orderDateItemLink()->ref }}</a></td>
                                        <td>
                                            {{ $orderItem->product['name'] }}
                                            @if ($orderItem->variant)
                                                - {{ $orderItem->variant['name'] }}
                                            @endif
                                         </td>
                                        <td class="text-right">{{ $orderItem->orderDateItemLink()->quantity }}</td>
                                        <td>{{ $orderItem->node['name'] }}</td>
                                        <td>{{ $orderItem->orderDateItemLink()->getDate()->date('Y-m-d') }}</td>
                                        <td class="text-right">{!! $orderItem->orderDateItemLink()->getPriceWithUnit() !!}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="card">
                    <div class="card-header">{{ trans('admin/user.orders') }}</div>
                    <div class="card-block">
                        {{ trans('admin/user.no_orders') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
