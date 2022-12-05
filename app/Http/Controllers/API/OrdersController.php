<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Models\Types\OrderStatus;
use App\Models\Types\ProductType;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\OrderResource;
use App\Models\Types\OrderItemStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Types\PaymentType;
use Exception;
use Faker\Provider\ar_EG\Payment;
use Illuminate\Foundation\Http\FormRequest;

class OrdersController extends Controller
{

    /**
     * Orders contructor
     */
    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $builder = Order::query();
        if (auth('api')->user()->isManager()) {
            $builder = Order::with('items')->latest();
        } else {
            $builder = auth('api')->user()->orders()->with('items')->latest();
        }

        return OrderResource::collection(
            $this->paginateBuilder($builder)
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreOrderRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreOrderRequest $request)
    {
        $user = auth('api')->user() ?? null;

        $order = DB::transaction(function () use ($request, $user) {

            $order = new Order();
            $order->total_price = Order::calculateTotalPrice($request);

            $payment = $order->makePayment(
                $user ? $user->customer->default_payment_type : $request->input('payment.type'),
                $user ? $user->customer->default_payment_reference : $request->input('payment.reference'),
                $order->total_price
            );

            $this->sendResponseNowIf(
                $payment->status() != 201,
                response()->json(
                    json_decode($payment->body()),
                    $payment->status()
                )
            );

            $ticket_number = Order::getNextTicket();
            $order->ticket_number = $ticket_number;

            $order->status = OrderStatus::PREPARING->value;
            $order->date = Carbon::now()->toDateString();
            $order->points_used_to_pay = $user ? $request->input('points_used_to_pay') : 0;

            $price_discount = $user ? Order::getPriceDiscount($order->points_used_to_pay) : 0;
            $order->total_paid_with_points = $user ? $price_discount : 0;
            $order->total_paid = ($order->total_price - $price_discount);
            $order->points_gained = $user ? Order::calculateGainedPoints($order->total_price) : 0;

            if ($user) {
                $user->customer->points -= $order->points_used_to_pay;
                $order->customer()->associate($user->customer)->save();
            }

            $order->payment_type = $user ? $user->customer->default_payment_type : $request->input('payment.type');
            $order->payment_reference = $user ? $user->customer->default_payment_reference : $request->input('payment.reference');

            $order->save();

            $this->assignOrderItems($request, $order);

            return $order;
        });

        return new OrderResource($order);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        return new OrderResource($order);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateOrderRequest  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {

        DB::transaction(function () use ($request, $order) {

            $order->fill($request->validated());

            $refund = $order->makeRefund();

            $this->sendResponseNowIf(
                $refund->status() != 201,
                response()->json(
                    json_decode($refund->body()),
                    $refund->status()
                )
            );

            $order->save();
        });

        return new OrderResource($order);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->delete();
        });

        return new OrderResource($order);
    }

    /**
     * Process order items
     *
     * @param FormRequest $request
     * @param Order $order
     * @return float
     */
    private function assignOrderItems(FormRequest $request, Order $order)
    {
        $i = 0;
        foreach ($request->items as $item) {
            /** @var Product $product */
            $product = Product::findOrFail($item);
            $order->items()->create([
                'order_id' => $order->id,
                'order_local_number' => ++$i,
                'product_id' => $product->id,
                'status' => $product->type == ProductType::HOT_DISH->value ? OrderItemStatus::WAITING : OrderItemStatus::READY,
                'price' => $product->price,
            ]);
        }
    }
}