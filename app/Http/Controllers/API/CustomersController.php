<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Http\Resources\CustomerResource;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Support\Facades\DB;

class CustomersController extends Controller
{

    public function index()
    {
        return CustomerResource::collection(Customer::latest()->paginate());
    }


    public function store(StoreCustomerRequest $request)
    {
        $customer = new Customer();
        $customer->fill($request->validated());

        $customer = DB::transaction(function () use ($customer) {
            $customer->save();
            return $customer;
        });

        return new CustomerResource($customer);
    }


    public function show(Customer $customer)
    {
        return new CustomerResource($customer);
    }


    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->fill($request->validated());

        DB::transaction(function () use ($request, $customer) {
            $customer->save();
        });

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        DB::transaction(function () use ($customer) {
            $customer->delete();
        });

        return new CustomerResource($customer);
    }
}
