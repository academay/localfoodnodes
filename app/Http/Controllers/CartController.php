<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Database\Eloquent\Collection;

use App\Http\Requests;
use App\Cart\Cart;
use App\Cart\CartDate;
use App\Cart\CartItem;
use App\Cart\CartDateItemLink;
use App\Order\Order;
use App\Order\OrderItem;
use App\Order\OrderDate;
use App\Order\OrderDateItemLink;
use App\Order\OrderStatus;

use App\Product\Product;
use App\Product\ProductVariant;
use App\Producer\Producer;
use App\Node\Node;

use App\Jobs\SendOrderEmails;

use \DateTime;

class CartController extends Controller
{
    /**
     * Cart index action.
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect('/login');
        }

        $cartDates = $user->cartDates();
        $today = new \DateTime(date('Y-m-d'));

        $cartDates->each(function($cartDate) use ($today) {
            if ($cartDate->date < $today) {
                $cartDateItemLinks = $cartDate->cartDateItemLinks();
                $cartDateItemLinks->each(function($cartDateItemLink) {
                    $cartDateItemLink->delete();
                });
                $cartDate->delete();
            }
        });

        return view('public/checkout', [
            'user' => $user
        ]);
    }

    /**
     * Cart add item action.
     *
     * @param Request $request
     */
    public function addItem(Request $request)
    {
        $errors = new MessageBag();

        $product = Product::find($request->input('product_id'));

        if ($product->variants()->count() > 0 && !$request->has('variant_id')) {
            $errors->add('variant_id', trans('public/product.no_variant'));
        }

        if (!$request->has('delivery_dates')) {
            $errors->add('delivery_dates', trans('public/product.no_delivery_dates'));
        }

        if (!$request->has('quantity')) {
            $errors->add('quantity', trans('public/product.no_quantity'));
        }

        if (!$errors->isEmpty()) {
            $request->session()->flash('message', [trans('public/product.required_fields_missing')]);
            return redirect()->back()->withInput()->withErrors($errors);
        }

        $user = Auth::user();
        $variant = $product->variants()->where('id', $request->input('variant_id'))->first();
        $producer = Producer::where('id', $product->producer_id)->first();
        $node = Node::find($request->input('node_id'));

        $this->addToCart($request, $user, $producer, $product, $variant, $node);

        if ($errors->isEmpty()) {
            $request->session()->flash('message', [trans('public/product.added_to_cart')]);
            $request->session()->flash('added_to_cart_modal', true);
            return redirect($node->permalink()->url);
        } else {
            return redirect()->back()->withInput()->withErrors($errors);
        }
    }

    /**
     * Update cart item quantity action.
     *
     * @param Request $request
     * @param int $cartItemId
     */
    public function updateItem(Request $request, $cartDateItemLinkId)
    {
        $quantity = $request->input('quantity');
        if (!$quantity) {
            return $this->removeItem($request, $cartDateItemLinkId);
        }

        $user = Auth::user();
        $cartDateItemLink = $user->cartDateItemLink($cartDateItemLinkId);

        if (!$cartDateItemLink) {
            $request->session()->flash('message', [trans('public/checkout.cart_item_update_failed')]);
            return redirect()->back();
        }

        $cartItem = $cartDateItemLink->getItem();

        $product = Product::find($cartItem->product['id']);
        $variant = $product->variants()->where('id', $cartItem->variant['id'])->first();
        $node = Node::find($cartItem->node['id']);

        if ($cartDateItemLink->getItem()->product['production_type'] === 'csa') {
            // CSA is all or nothing
            $user->cartItems($cartDateItemLink->getItem()->product['id'])->map(function($cartItem) use ($request, $product, $variant, $node) {
                $cartItem->cartDateItemLinks()->each(function($cartDateItemLink) use ($request, $product, $variant, $node) {
                    $this->validateAndUpdateCartDateItemLink($request, $cartDateItemLink, $product, $variant, $node);
                });
            });
        } else {
            $this->validateAndUpdateCartDateItemLink($request, $cartDateItemLink, $product, $variant, $node);
        }

        return redirect('/checkout');
    }

    /**
     * Cart remove cart item action.
     */
    public function removeItem(Request $request, $cartDateItemLinkId)
    {
        $user = Auth::user();

        $cartDateItemLink = $user->cartDateItemLink($cartDateItemLinkId);

        if (isset($cartDateItemLink->getItem()->product['production_type']) && $cartDateItemLink->getItem()->product['production_type'] === 'csa') {
            // CSA is all or nothing
            $user->cartItems($cartDateItemLink->getItem()->product['id'])->map(function($cartItem) {
                $cartItem->cartDateItemLinks()->each(function($cartDateItemLink) {
                    $cartDateItemLink->delete();
                });
            });
        } else {
            $cartDateItemLink->delete();
        }

        return redirect()->back();
    }

    /**
     * Add item to cart.
     *
     * @param Request $request
     * @param User $user
     * @param Producer $producer
     * @param Product $product
     * @param Variant $variant
     * @param Node $node
     */
    private function addToCart($request, $user, $producer, $product, $variant, $node)
    {
        // Get existing cart dates for node and create the ones missing
        $existingCartDates = $user->cartDates();
        $this->createCartDates($request, $existingCartDates, $user, $node);
        $cartDates = $user->cartDates($request->input('delivery_dates'));

        // Check if item's already in cart
        if ($variant) {
            $cartItem = $user->cartItem($product->id, $node->id, $variant->id);
        } else {
            $cartItem = $user->cartItem($product->id, $node->id);
        }

        if (!$cartItem) {
            $cartItem = $this->createCartItem($request, $user, $producer, $product, $node, $variant);
        } else if ($request->input('message')) {
            $cartItem->message = $request->input('message');
            $cartItem->save();
        }

        $this->validateAndCreateCartDateItemLink($request, $user, $cartDates, $cartItem, $product, $variant, $node);
    }

    /**
     * Validate quantity for requested date and adjust quantity if needed, and then create links.
     *
     * @param Request $request
     * @param User $user
     * @param Collection $cartDates
     * @param CartItem $cartItem
     * @param Product $product
     * @param Variant $variant
     * @param Node $node
     */
    private function validateAndCreateCartDateItemLink($request, $user, $cartDates, $cartItem, $product, $variant, $node)
    {
        $errors = new Collection();

        $existingCartDateItemLinks = new Collection();
        $cartItem->cartDateItemLinks()->each(function($cartDateItemLink) use (&$existingCartDateItemLinks) {
            $date = $cartDateItemLink->getDate()->date('Y-m-d');
            $existingCartDateItemLinks->put($date, $cartDateItemLink);
        });

        $cartQuantity = 0;
        foreach ($cartDates as $cartDate) {
            $existingCartDateItemLink = $existingCartDateItemLinks->get($cartDate->date('Y-m-d'));

            $quantity = $request->input('quantity');

            // If date item link exists we just update the quantity
            if ($existingCartDateItemLink) {
                $quantity = $existingCartDateItemLink->quantity + $quantity;
            }

            $deliveryLink = $product->deliveryLink($node->id, $cartDate->date('Y-m-d'));
            $availableQuantity = $deliveryLink->getAvailableQuantity($variant, $cartQuantity);

            if ($availableQuantity < $quantity) {
                $errors->push(trans('public/product.quantity_changed', [
                    'date' => $cartDate->date('Y-m-d')
                ]));
                $quantity = $availableQuantity;
                $cartQuantity += $quantity;
            }

            if (!$errors->isEmpty()) {
                $request->session()->flash('error', $errors->toArray());
            }

            if ($existingCartDateItemLink) {
                $existingCartDateItemLink->quantity = $quantity;
                $existingCartDateItemLink->save();
            } else {
                $this->createCartDateItemLink($user, $cartDate, $cartItem, $quantity);
            }
        }
    }

    /**
     * Validate and update CartDateItemLink.
     *
     * @param Request $request
     * @param CartDateItemLink $cartDateItemLink
     * @param Product $product
     * @param Variant $variant
     * @param Node $node
     */
    private function validateAndUpdateCartDateItemLink($request, $cartDateItemLink, $product, $variant, $node)
    {
        $errors = new Collection();

        $quantity = $request->input('quantity');
        $deliveryLink = $product->deliveryLink($node->id, $cartDateItemLink->getDate()->date('Y-m-d'));
        $availableQuantity = $deliveryLink->getAvailableQuantity($variant);

        if ($availableQuantity < $quantity) {
            $errors->push(trans('public/product.quantity_changed', [
                'date' => $cartDate->date('Y-m-d')
            ]));
            $quantity = $availableQuantity;
        }

        if (!$errors->isEmpty()) {
            $request->session()->flash('error', $errors->toArray());
        }

        $cartDateItemLink->quantity = $quantity;
        $cartDateItemLink->save();
    }

    /**
     * Create new cart item.
     *
     * @param Request $request
     * @param Product $product
     * @param Variant $variant
     * @param Producer $producer
     * @return CartItem
     */
    private function createCartItem($request, $user, $producer, $product, $node, $variant = null) {
        return CartItem::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'node' => $node->getInfoForOrder(),
            'producer_id' => $producer->id,
            'producer' => $producer->getInfoForOrder(),
            'product_id' => $product->id,
            'product' => $product->getInfoForOrder(),
            'variant_id' => $variant ? $variant->id : null,
            'variant' => $variant ? $variant->getInfoForOrder() : null,
            'message' => $request->input('message'),
        ]);
    }

    /**
     * Create new cart date.
     *
     * @param Request $request
     * @param Collection $allCartDates
     * @param Node $node
     * @return Collection
     */
    private function createCartDates($request, $existingCartDates, $user)
    {
        $createdDates = new Collection();

        $newDates = collect(array_diff($request->input('delivery_dates'), $existingCartDates->map(function($cartDate) {
            return $cartDate->date('Y-m-d');
        })->toArray()));

        // Create new dates
        $newDates->each(function($date) use ($user, &$createdDates) {
            $cartDate = CartDate::create([
                'user_id' => $user->id,
                'date' => $date,
            ]);

            $createdDates->push($cartDate);
        });

        return $createdDates;
    }

    /**
     * Create new cart item date link.
     *
     * @param Cart $cart
     * @param CartItem $cartItem
     * @param CartDate $cartDate
     * @return CartDateItemLink
     */
    private function createCartDateItemLink($user, $cartDate, $cartItem, $quantity)
    {
        return CartDateItemLink::create([
            'user_id' => $user->id,
            'cart_item_id' => $cartItem->id,
            'cart_date_id' => $cartDate->id,
            'quantity' => $quantity,
            'ref' => 'LFN-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4)
        ]);
    }
}
