@extends('admin.layout')

@section('title', join(array_keys($breadcrumbs), ' - '))

@section('content')
    @include('admin.page-header')

    <form action="/account/user/password/update" method="post">
        <div class="row">
            <div class="col-12 col-xl-8">
                {{ csrf_field() }}
                <div class="card">
                    <div class="card-header">{{ trans('admin/user.change_password') }}</div>
                    <div class="card-block">
                        <div class="form-group">
                            <label class="form-control-label" for="password">{{ trans('admin/user.new_password') }}</label>
                            <input type="password" name="password" class="form-control" id="password" placeholder="{{ trans('admin/user.password') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @component('admin.form-control-bar')
            <button type="submit" class="btn btn-success">{{ trans('admin/user.change_password') }}</button>
        @endcomponent
    </form>
@endsection
