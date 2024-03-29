<?php

namespace App\Http\Controllers\API;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Models\Types\OrderStatus;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Requests\UpdateOrderItemRequest;
use App\Models\Types\OrderItemStatus;
use Illuminate\Support\Facades\Log;

class OrderItemController extends Controller
{

    /**
     * Contructor
     *
     */
    public function __construct()
    {
        $this->authorizeResource(OrderItem::class, 'orderitem');
    }


    /**
     * List all records
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $builder = $request->user('api')->prepared()->orderBy('id', 'DESC');

        return OrderItemResource::collection(
            $this->paginateBuilder($builder, $request->input('size'))
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OrderItem  $orderItem
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateOrderItemRequest $request, OrderItem $orderItem)
    {
        abort_if(
            $orderItem->order->status != OrderStatus::PREPARING->value,
            400,
            "Order Item cannot be changed. Order state forbids."
        );

        $updated = DB::transaction(function () use ($request, $orderItem) {
            $orderItem->update($request->safe()->only('status'));
            switch ($request->input('status')) {
                case OrderItemStatus::READY->value:
                    $orderItem->preparated()->associate($request->user('api'));
                    break;
                case OrderItemStatus::PREPARING->value:
                case OrderItemStatus::WAITING->value:
                    $orderItem->preparated()->dissociate();
                    break;
            }

            return $orderItem->save();
        });

        return (new OrderItemResource($orderItem))->additional([
            'message' => $updated ? "Item updated successfully." : "Item was not updated."
        ]);
    }
}
