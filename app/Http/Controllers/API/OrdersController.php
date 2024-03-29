<?php

namespace App\Http\Controllers\API;

use Exception;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Types\OrderStatus;
use App\Models\Types\ProductType;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Types\OrderItemStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
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
    public function index(Request $request)
    {
        $builder = Order::query();
        if ($request->user('api')->isManager()) {
            $builder = $builder->with('items')->latest();
        } else if ($request->user('api')->isDelivery()) {
            $builder = $request->user('api')->delivered()->latest()->with('items');
        } else if ($request->user('api')->isCustomer()) {
            $builder = $request->user('api')->orders()->with('items')->latest();
        }

        return OrderResource::collection(
            $this->paginateBuilder($builder, $request->input('size'))
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

        $total_price = Order::calculateTotalPrice($request);

        // ! Out of transaction to avoid computational costs
        $payment = Order::makePayment(
            $user ? $user->customer->default_payment_type : $request->input('payment.type'),
            $user ? $user->customer->default_payment_reference : $request->input('payment.reference'),
            $total_price
        );

        $this->sendResponseNowIf(
            $payment->status() != 201,
            response()->json(
                json_decode($payment->body()),
                $payment->status()
            )
        );

        // ! When paid we proceed to create the order, because the error of the database failing is low

        try {
            $order = DB::transaction(function () use ($request, $user, $total_price) {
                $order = new Order();
                $order->total_price = $total_price;

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
                    $user->customer->points += $order->points_gained;
                    $order->customer()->associate($user->customer)->save();
                    $user->save();
                    $user->customer->save();
                }

                $order->payment_type = $user ? $user->customer->default_payment_type : $request->input('payment.type');
                $order->payment_reference = $user ? $user->customer->default_payment_reference : $request->input('payment.reference');

                $order->save();

                $this->assignOrderItems($request, $order);

                return $order;
            });
        } catch (Exception $ex) {
            Order::makeRefund(
                $user ? $user->customer->default_payment_type : $request->input('payment.type'),
                $user ? $user->customer->default_payment_reference : $request->input('payment.reference'),
                $total_price
            );
            abort(500, "Unable to create order.");
        }

        return (new OrderResource($order))->additional([
            'message' => "Order created with success."
        ]);
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

        abort_if($order->status == OrderStatus::CANCELED->value, 400, "Order is canceled.");

        $saved = DB::transaction(function () use ($request, $order) {

            if ($request->input('status') == OrderStatus::CANCELED->value && $request->user('api')->isManager()) {

                $refund = Order::makeRefund(
                    $order->payment_type,
                    $order->payment_reference,
                    $order->total_paid
                );

                $this->sendResponseNowIf(
                    $refund->status() != 201,
                    response()->json(
                        json_decode($refund->body()),
                        $refund->status()
                    )
                );

                if ($order->customer && $order->points_gained > 0) {
                    $order->customer->points -= $order->points_gained;
                    $order->customer->save();
                }

                $order->update($request->safe()->only('status'));
            }

            abort_if($order->status == OrderStatus::DELIVERED->value, 400, "Order has been delivered.");

            if (
                $request->user('api')->isDelivery()
                && $request->input('status') != OrderStatus::CANCELED->value
            ) {

                switch ($request->input('status')) {
                    case OrderStatus::DELIVERED->value:
                        $order->delivered()->associate($request->user('api'));
                        break;
                    case OrderStatus::READY->value:
                        abort_if(
                            $order->items()
                                ->getQuery()
                                ->where('status', '!=', OrderItemStatus::READY->value)
                                ->get()
                                ->count() > 0,
                            400,
                            "Items are not ready."
                        );
                        $order->delivered()->dissociate();
                        break;
                    case OrderStatus::PREPARING->value:
                        $order->delivered()->dissociate();
                        break;
                }

                $order->update($request->safe()->only('status'));
            }

            return $order->save();
        });

        return (new OrderResource($order))->additional([
            'message' => $saved ? "Order updated with success." : "Order was not updated."
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        $deleted = DB::transaction(function () use ($order) {
            $order->items()->delete();
            return $order->delete();
        });

        return (new OrderResource($order))->additional([
            'message' => $deleted ? "Order deleted with success." : "Order was not deleted."
        ]);
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
                'status' => $product->type == ProductType::HOT_DISH->value ? OrderItemStatus::WAITING->value : OrderItemStatus::READY->value,
                'price' => $product->price,
            ]);
        }
    }
}
