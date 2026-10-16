# Livewire

Unreleased recipe; check [release availability](../capabilities.md).

Install a compatible Livewire version in the consuming Laravel application. The UI
below is illustrative; the [shared service](index.md) is what Testbench executes.
Configure its catalog closure and copy it into App\Support before using this fragment.
Livewire's standard ValidationException handling exposes server validation errors.

```php
use App\Support\CartActions;
use Livewire\Component;
use OfflineAgency\LaravelCart\Exceptions\CartException;

class CartPanel extends Component
{
    public int $productId = 1;
    public string $size = 'S';
    public int $quantity = 1;

    public function add(CartActions $actions): void
    {
        $this->validate(['quantity' => 'required|integer|between:1,99']);
        $this->attempt(fn () => $actions->add($this->productId, $this->size, $this->quantity));
    }

    public function updateQuantity(string $rowId, CartActions $actions): void
    {
        $this->validate(['quantity' => 'required|integer|between:1,99']);
        $this->attempt(fn () => $actions->update($rowId, $this->quantity));
    }

    public function remove(string $rowId, CartActions $actions): void
    {
        $this->attempt(fn () => $actions->remove($rowId));
    }

    private function attempt(Closure $action): void
    {
        try { $action(); }
        catch (CartException $error) { $this->addError('cart', $error->getMessage()); }
    }

    public function render(CartActions $actions)
    {
        return view('livewire.cart-panel', ['cart' => $actions->summary()]);
    }
}
```

```blade
<div>
    <select wire:model="size"><option>S</option><option>L</option></select>
    <input type="number" wire:model="quantity" min="1" max="99">
    <button wire:click="add">Add</button>
    @foreach ($cart['items'] as $item)
        <p>{{ $item['name'] }}</p>
        <button wire:click="updateQuantity('{{ $item['rowId'] }}')">Set quantity</button>
        <button wire:click="remove('{{ $item['rowId'] }}')">Remove</button>
    @endforeach
    @foreach ($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
    <output>{{ $cart['total'] }}</output>
</div>
```

Each action rerenders current totals; no client-supplied price is accepted. Product
and row identifiers remain untrusted selectors checked by the server service.
