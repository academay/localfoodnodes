<div class="row">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header">
                {{ trans('admin/product.product') }}
            </div>
            <div class="card-block">
                <div class="form-group">
                    <label class="form-control-label" for="name">
                        {{ trans('admin/product.product_name') }}
                        @include('admin.field-error', ['field' => 'name'])
                    </label>
                    <input type="text" name="name" class="form-control" id="name" placeholder="{{ trans('admin/product.product_name_placeholder') }}" value="{{ $product->name or '' }}">
                </div>

                <div class="form-group">
                    <label class="form-control-label" for="info">
                        {{ trans('admin/product.product_description') }}
                        @include('admin.field-error', ['field' => 'info'])
                    </label>
                    <textarea class="form-control wysiwyg" id="info" name="info" rows="5" placeholder="{{ trans('admin/product.product_description_placeholder') }}">{{ $product->info or '' }}</textarea>
                </div>

                <div class="form-group">
                    <div class="{{ $product->variants()->count() > 0 ? 'disabled' : '' }}">
                        <label class="form-control-label" for="price">
                            {{ trans('admin/product.enter_price_one_product') }}
                            @include('admin.field-error', ['field' => 'price'])
                        </label>
                        <input type="number" min="0" name="price" class="form-control" id="price" placeholder="Price" value="{{ $product->price or '' }}">
                    </div>
                    @if ($product->variants()->count() > 0)
                        <div class="form-text">
                            {{ trans('admin/product.price_on_variants') }}
                        </div>
                    @endif
                </div>

                <div class="form-group" id="price-unit">
                    <label class="form-control-label" for="price">
                        {{ trans('admin/product.price_per') }}
                        @include('admin.field-error', ['field' => 'price_unit'])
                    </label>
                    <select class="form-control" name="price_unit">
                        @foreach (UnitsHelper::getPriceUnits() as $key => $label)
                            <option value="{{ $key }}" {{ $product->price_unit === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="package-amount">
                    <label class="form-control-label" for="package_amount">
                        {{ trans('admin/product.estimate_package_amount') }}
                        @include('admin.field-error', ['field' => 'package_amount'])
                    </label>
                    <input type="text" name="package_amount" class="form-control" id="price" placeholder="{{ trans('admin/product.estimate_package_amount') }}" value="{{ $product->package_amount or '' }}">
                    <div class="form-text text-muted">
                        {{ trans('admin/product.package_amount_info') }}
                    </div>
                </div>

                <script>
                    jQuery(document).ready(function() {
                        var packageAmount = function(val) {
                            if (val !== 'product') {
                                $('#package-amount').show();
                            } else {
                                $('#package-amount').hide();
                            }
                        };

                        $('#price-unit select').on('change', function(event) {
                            packageAmount($(this).val());
                        });

                        packageAmount($('#price-unit select').val());
                    });
                </script>

                <div class="form-group">
                    <label class="form-control-label">
                        {{ trans('admin/product.select_tags') }}
                    </label>
                    <div class="tags">
                        @foreach ($tags as $key => $tag)
                            <div class="tag">
                                <input id="label-{{ $key }}" type="checkbox" name="tags[]" value="{{ $key }}" {{ $product->tag($key) ? 'checked' : '' }}>
                                <label for="label-{{ $key }}">{{ ucfirst($tag) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>


        <!-- Images -->
        @include('admin.image-card', [
            'images' => $product->images(),
            'deleteUrl' => '/account/image/{imageId}/delete',
            'limit' => 4,
        ])

        @include('admin.product.product.other-options')
    </div>

    <div class="col-12 col-xl-4">
        @include('admin.product.product.how-does-it-work')
    </div>
</div>
