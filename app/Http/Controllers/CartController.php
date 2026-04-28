<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->role === '2001') {
            // User with role 2001: return only their own carts
            $cartItems = Cart::where('user_id', $user->id)->get()->map(function ($cart) {
                $productIds = collect($cart->products)->pluck('product_id')->toArray();
                $productsDetails = Product::whereIn('id', $productIds)->get()->keyBy('id');

                $productsWithDetails = collect($cart->products)->map(function ($item) use ($productsDetails) {
                    $product = $productsDetails->get($item['product_id']);
                    return [
                        'product_id' => $item['product_id'],
                        'count' => $item['count'],
                        'product' => $product ? $product->toArray() : null,
                    ];
                });

                return [
                    'id' => $cart->id,
                    'products' => $productsWithDetails,
                    'location' => $cart->location,
                    'mobile' => $cart->mobile,
                    'status' => $cart->status,
                    'result' => $cart->result,
                    'created_at' => $cart->created_at,
                ];
            });
        } else {
            // Other roles: return all carts with user info
            $cartItems = Cart::with('Users:id,name,email')->get()->map(function ($cart) {
                $productIds = collect($cart->products)->pluck('product_id')->toArray();
                $productsDetails = Product::whereIn('id', $productIds)->get()->keyBy('id');

                $productsWithDetails = collect($cart->products)->map(function ($item) use ($productsDetails) {
                    $product = $productsDetails->get($item['product_id']);
                    return [
                        'product_id' => $item['product_id'],
                        'count' => $item['count'],
                        'product' => $product ? $product->toArray() : null, // تفاصيل المنتج الكاملة
                    ];
                });

                return [
                    'id' => $cart->id,
                    'user' => $cart->Users ? $cart->Users->toArray() : ['id' => $cart->user_id, 'name' => 'Unknown', 'email' => ''],
                    'products' => $productsWithDetails,
                    'location' => $cart->location,
                    'mobile' => $cart->mobile,
                    'status' => $cart->status,
                    'result' => $cart->result,
                    'created_at' => $cart->created_at,
                ];
            });
        }

        return response()->json($cartItems);
    }

    /**
     * Validate cart items
     */
    public function check(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.count' => 'required|numeric|min:1',
            'items.*.product_id' => 'required|exists:products,id',
        ]);

        $errors = [];
        $successMessages = [];

        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                $errors[] = "Product {$item['product_id']} not found";
                continue;
            }

            $successMessages[] = "Product {$item['product_id']} is available for cart";
        }

        if (!empty($errors)) {
            return response()->json(['errors' => $errors, 'success' => $successMessages], 400);
        }

        return response()->json(['message' => 'All items validated successfully', 'success' => $successMessages], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.count' => 'required|numeric|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'location' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'status' => 'nullable|string|max:255', // validation لحقل status
            'result' => 'nullable|string|max:255', // validation لحقل result
        ]);

        $errors = [];
        $productsArray = [];

        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                $errors[] = "Product {$item['product_id']} not found";
                continue;
            }

            // إضافة المنتج إلى المصفوفة
            $productsArray[] = [
                'product_id' => $item['product_id'],
                'count' => $item['count'],
            ];
        }

        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 400);
        }

        // إنشاء صف جديد دائماً
        $cart = Cart::create([
            'user_id' => Auth::user()->id,
            'products' => $productsArray,
            'location' => $request->location,
            'mobile' => $request->mobile,
            'status' => $request->status ?? 'pending', // إضافة status مع قيمة افتراضية 'pending'
            'result' => $request->result ?? null,
        ]);

        return response()->json([
            'message' => 'Cart created successfully',
            'cart' => $cart
        ], 201);
    }

    /**
     * Display the specified cart for the authenticated user.
     */
    public function show($cartId)
    {
        $user = Auth::user();

        if ($user->role === '2001') {
            $cart = Cart::where('id', $cartId)
                ->where('user_id', $user->id)
                ->first();
        } else {
            $cart = Cart::find($cartId);
        }

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        return response()->json($cart);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cart $cart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cart $cart)
    {
        //
    }

    /**
     * Update the status of a specific cart for a user.
     */
    public function updateStatus(Request $request, $cartId)
    {
        $request->validate([
            'status' => 'nullable|string|max:255',
            'result' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        if ($user->role === '2001') {
            $cart = Cart::where('id', $cartId)->where('user_id', $user->id)->first();
        } else {
            $cart = Cart::find($cartId);
        }

        if (!$cart) {
            return response()->json(['message' => 'Cart not found or not authorized'], 404);
        }

        $updateData = [];
        if ($request->has('status')) {
            $updateData['status'] = $request->status;
        }
        if ($request->has('result')) {
            $updateData['result'] = $request->result;
        }

        if (empty($updateData)) {
            return response()->json(['message' => 'Nothing to update'], 400);
        }

        $cart->update($updateData);

        return response()->json(['message' => 'Cart updated successfully', 'cart' => $cart]);
    }

    /**
     * Remove the specified cart from storage.
     */
    public function destroy($cartId)
    {
        $user = Auth::user();

        if ($user->role === '2001') {
            $cart = Cart::where('id', $cartId)
                ->where('user_id', $user->id)
                ->first();
        } else {
            $cart = Cart::find($cartId);
        }

        if (!$cart) {
            return response()->json(['message' => 'Cart not found or not authorized'], 404);
        }

        $cart->delete();

        return response()->json(['message' => 'Cart deleted successfully']);
    }
}
