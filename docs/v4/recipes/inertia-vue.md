# Inertia with Vue

Unreleased recipe; check [release availability](../capabilities.md).

The [shared CartActions service](index.md) is executed in Testbench. The controller,
routes and Vue below are illustrative, requiring compatible Inertia/Vue dependencies
in the application; this package does not install or claim to have tested them.

Example application controller methods (inject CartActions as `$actions`):

```php
public function show(CartActions $actions)
{
    return Inertia::render('Cart', ['cart' => $actions->summary()]);
}

public function store(Request $request, CartActions $actions)
{
    $data = $request->validate(['productId' => 'required|integer|min:1',
        'size' => 'required|string|max:30', 'quantity' => 'required|integer|between:1,99']);
    try { $actions->add((int) $data['productId'], $data['size'], (int) $data['quantity']); }
    catch (CartException $error) { throw ValidationException::withMessages(['cart' => $error->getMessage()]); }
    return back();
}

public function update(Request $request, string $rowId, CartActions $actions)
{
    $data = $request->validate(['quantity' => 'required|integer|between:1,99']);
    try { $actions->update($rowId, (int) $data['quantity']); }
    catch (CartException $error) { throw ValidationException::withMessages(['cart' => $error->getMessage()]); }
    return back();
}

public function destroy(string $rowId, CartActions $actions)
{
    try { $actions->remove($rowId); }
    catch (CartException $error) { throw ValidationException::withMessages(['cart' => $error->getMessage()]); }
    return back();
}
```

Import Request, ValidationException, Inertia, the application CartActions, and package
CartException. Register GET/POST `/cart` and PATCH/DELETE `/cart/{rowId}` in the web
group with CSRF/session middleware. Add authentication if the page requires it.
ValidationException from catalog/service is rendered through Inertia's error props.
The summary is the actual CartResource array, not a hand-maintained response shape.

```vue
<script setup>
import { router, useForm } from '@inertiajs/vue3'
defineProps({ cart: Object })
const form = useForm({ productId: 1, size: 'S', quantity: 1 })
const update = rowId => form.patch(`/cart/${encodeURIComponent(rowId)}`)
const remove = rowId => router.delete(`/cart/${encodeURIComponent(rowId)}`)
</script>
<template>
  <form @submit.prevent="form.post('/cart')">
    <select v-model="form.size"><option>S</option><option>L</option></select>
    <input v-model.number="form.quantity" type="number" min="1" max="99">
    <button :disabled="form.processing">Add</button>
  </form>
  <p v-for="(error, key) in form.errors" :key="key" role="alert">{{ error }}</p>
  <p v-for="(error, key) in $page.props.errors" :key="key" role="alert">{{ error }}</p>
  <div v-for="item in cart.items" :key="item.rowId">
    {{ item.name }}
    <button @click="update(item.rowId)">Set quantity</button>
    <button @click="remove(item.rowId)">Remove</button>
  </div>
  <output>{{ cart.total }}</output>
</template>
```

Redirects refresh the page props after mutations. For payable tax details explicitly
add `$cart->payableTaxBreakdown()->toArray()` as a separate prop; CartResource keeps
its existing response contract. See [allocation limits](../post-discount-tax.md).
