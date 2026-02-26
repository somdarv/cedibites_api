<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::with(['user', 'addresses'])
            ->when($request->is_guest !== null, fn ($query) => $query->where('is_guest', $request->boolean('is_guest')))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->paginated(CustomerResource::collection($customers));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $customer = Customer::create($request->validated());

            return response()->created(
                new CustomerResource($customer->load('user'))
            );
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['user', 'addresses', 'orders']);

        return response()->success(new CustomerResource($customer));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        try {
            $customer->update($request->validated());

            return response()->success(
                new CustomerResource($customer->fresh('user'))
            );
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $customer->delete();

            return response()->deleted();
        } catch (\Exception $e) {
            return response()->server_error();
        }
    }

    /**
     * Get customer orders.
     */
    public function orders(Request $request, Customer $customer): JsonResponse
    {
        $query = $customer->orders()->with(['orderItems.menuItemSize.menuItem', 'payment', 'branch']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate($request->per_page ?? 15);

        return response()->success(
            OrderResource::collection($orders)->response()->getData(true),
            'Customer orders retrieved successfully.'
        );
    }
}
