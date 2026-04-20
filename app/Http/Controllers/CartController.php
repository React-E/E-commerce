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

        if ($user->role === '1995') {
            // Admin: return all cart items grouped by user
            $cartItems = Cart::with('Products', 'Users:id,name,email')
                ->get()
                ->groupBy('user_id')
                ->map(function ($items, $userId) {
                    $firstItem = $items->first();
                    $userData = $firstItem->Users ? $firstItem->Users->toArray() : ['id' => $userId, 'name' => 'Unknown', 'email' => ''];
                    return [
                        'user_id' => $userId,
                        'user' => array_merge($userData, [
                            'location' => $firstItem->location,
                            'mobile' => $firstItem->mobile,
                        ]),
                        'products' => $items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'count' => $item->count,
                                'product' => $item->Products,
                            ];
                        }),
                    ];
                })
                ->values();
        } else {
            // Regular user: return only their cart items
            $cartItems = Cart::with('Products')
                ->where('user_id', $user->id)
                ->get();
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
            'items.*.location' => 'required|string|max:255', // validation للموقع
            'items.*.mobile' => 'required|string|max:15', // validation لرقم الموبايل
        ]);

        $errors = [];
        $successMessages = [];

        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                $errors[] = "Product {$item['product_id']} not found";
                continue;
            }

            $cart = Cart::where('user_id', Auth::id())
                ->where('product_id', $item['product_id'])
                ->first();

            if ($cart) {
                $newCount = $cart->count + $item['count'];
                $cart->update([
                    'count' => $newCount,
                    'location' => $item['location'] ?? $cart->location, // تحديث الموقع إذا تم إرساله
                    'mobile' => $item['mobile'] ?? $cart->mobile, // تحديث الموبايل إذا تم إرساله
                ]);
                $successMessages[] = "Cart updated for product {$item['product_id']}";
            } else {
                Cart::create([
                    'count' => $item['count'],
                    'user_id' => Auth::user()->id,
                    'product_id' => $item['product_id'],
                    'location' => $item['location'] ?? null, // إضافة الموقع
                    'mobile' => $item['mobile'] ?? null, // إضافة الموبايل
                ]);
                $successMessages[] = "Cart created for product {$item['product_id']}";
            }
        }

        if (!empty($errors)) {
            return response()->json(['errors' => $errors, 'success' => $successMessages], 400);
        }

        // جلب السلة المحدثة مع تفاصيل المنتجات
        $updatedCart = Cart::with('Products')->where('user_id', Auth::id())->get();

        return response()->json([
            'message' => 'All items processed successfully',
            'success' => $successMessages,
            'cart' => $updatedCart
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
        //
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
     * Remove the specified resource from storage.
     */
    public function destroy(Cart $cart)
    {
        //
    }
}
