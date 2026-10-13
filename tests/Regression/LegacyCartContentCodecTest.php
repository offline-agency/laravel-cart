<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\CartItemOptions;
use OfflineAgency\LaravelCart\Enums\CouponType;
use OfflineAgency\LaravelCart\Exceptions\CartStorageException;
use OfflineAgency\LaravelCart\Support\CartContentCodec;
use OfflineAgency\LaravelCart\Support\Coerce;
use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use OfflineAgency\LaravelCart\Tests\Fixtures\Codec\CodecModel;
use OfflineAgency\LaravelCart\Tests\Fixtures\Codec\ExplodingBuyable;
use OfflineAgency\LaravelCart\Tests\Fixtures\Codec\WakeupGadget;

/**
 * Defect 1: the legacy `cart` table stored raw serialize() output (with NUL
 * bytes) in a text column, which PostgreSQL cannot round-trip.
 */
function regCodecUsesPostgres(): bool
{
    return DB::connection('testing')->getDriverName() === 'pgsql';
}

function regCodecCart(): Cart
{
    return resolveCart();
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
    WakeupGadget::$wokenUp = false;
});

it('stores the legacy cart content without NUL bytes', function (): void {
    $cart = regCodecCart();
    $cart->add('1', 'Product', 'Sub', 2, 10.0, 12.2, 2.2, options: ['size' => 'M']);

    $cart->store('nul-check');

    $stored = Coerce::string(DB::table('cart')->where('identifier', 'nul-check')->value('content'));

    expect($stored)->not->toContain("\0")
        ->and($stored)->toStartWith(CartContentCodec::PREFIX);
});

it('round-trips a stored cart through the legacy table on every driver', function (): void {
    $cart = regCodecCart();
    $item = $cart->add('1', 'Product', 'Sub', 2, 10.0, 12.2, 2.2, options: ['size' => 'M']);
    $cart->addItemCoupon($item->rowId, 'TEN', 'percentage', 10.0);
    $expected = $cart->content()->map(fn (CartItem $i) => $i->toArray())->all();

    $cart->store('roundtrip');
    $cart->destroy();
    $cart->restore('roundtrip');

    expect($cart->content()->map(fn (CartItem $i) => $i->toArray())->all())->toEqual($expected)
        ->and($cart->get($item->rowId)->appliedCoupons['TEN']->discountValue)->toBe(1.22);
});

it('keeps a Buyable associated model when restoring a stored cart', function (): void {
    $cart = regCodecCart();
    $item = $cart->add(new BuyableProduct(7, 'Buyable'));

    $cart->store('buyable');
    $cart->destroy();
    $cart->restore('buyable');

    expect($cart->get($item->rowId)->model)->toBeInstanceOf(BuyableProduct::class);
});

it('still restores legacy raw serialized rows', function (): void {
    $item = new CartItem(id: '9', name: 'Legacy', subtitle: '', qty: 3, price: 5.0, totalPrice: 5.0, vat: 0.0);

    DB::table('cart')->insert([
        'identifier' => 'legacy-raw',
        'instance' => 'default',
        'content' => serialize(collect([$item->rowId => $item])),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cart = regCodecCart();
    $cart->restore('legacy-raw');

    expect($cart->count())->toBe(3)
        ->and($cart->get($item->rowId)->name)->toBe('Legacy');
})->skip(fn (): bool => regCodecUsesPostgres(), 'PostgreSQL cannot hold raw serialize() output, so no legacy rows exist there.');

it('throws a storage exception instead of a PHP warning for malformed content', function (string $content): void {
    DB::table('cart')->insert([
        'identifier' => 'broken',
        'instance' => 'default',
        'content' => $content,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => regCodecCart()->restore('broken'))->toThrow(CartStorageException::class);

    expect(DB::table('cart')->where('identifier', 'broken')->exists())->toBeTrue();
})->with([
    'garbage' => 'definitely not serialized',
    'bad base64' => 'lcart:b64:%%%',
    'not a collection' => 'lcart:b64:'.base64_encode(serialize('a string')),
]);

it('does not instantiate classes outside the allow-list', function (): void {
    $item = new CartItem(id: '1', name: 'Gadget carrier', subtitle: '', qty: 1, price: 1.0, totalPrice: 1.0, vat: 0.0);
    $item->model = new WakeupGadget;

    DB::table('cart')->insert([
        'identifier' => 'gadget',
        'instance' => 'default',
        'content' => CartContentCodec::encode(collect([$item->rowId => $item])),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    regCodecCart()->restore('gadget');

    expect(WakeupGadget::$wokenUp)->toBeFalse()
        ->and(regCodecCart()->get($item->rowId)->model)->toBeInstanceOf(__PHP_Incomplete_Class::class);
});

it('lets the console commands read carts written by Cart::store()', function (): void {
    $cart = regCodecCart();
    $cart->add('1', 'Product', '', 2, 10.0, 10.0, 0.0);
    $cart->store('cmd-user');

    $this->artisan('cart:list', ['--json' => true])
        ->expectsOutputToContain('"items": 1')
        ->assertExitCode(0);

    $this->artisan('cart:inspect', ['identifier' => 'cmd-user', '--json' => true])
        ->expectsOutputToContain('"name": "Product"')
        ->assertExitCode(0);

    $this->artisan('cart:stats', ['--json' => true])
        ->expectsOutputToContain('"avgItems": 1')
        ->assertExitCode(0);

    $path = sys_get_temp_dir().'/reg-codec-export-'.uniqid().'.json';
    $this->artisan('cart:export', ['--path' => $path])->assertExitCode(0);
    $export = decodeJson(file_get_contents($path));
    @unlink($path);

    expect(data_get($export, 'carts.0.items.0.name'))->toBe('Product')
        ->and(data_get($export, 'carts.0'))->not->toHaveKey('error');
});

it('keeps flagging undecodable content in the console commands', function (): void {
    DB::table('cart')->insert([
        'identifier' => 'broken-cmd',
        'instance' => 'default',
        'content' => 'lcart:b64:%%%',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('cart:list', ['--json' => true])
        ->expectsOutputToContain('"items": null')
        ->assertExitCode(0);

    $this->artisan('cart:inspect', ['identifier' => 'broken-cmd'])->assertExitCode(1);

    $path = sys_get_temp_dir().'/reg-codec-export-'.uniqid().'.json';
    $this->artisan('cart:export', ['--path' => $path])->assertExitCode(0);
    $export = decodeJson(file_get_contents($path));
    @unlink($path);

    expect(data_get($export, 'carts.0.error'))->toBe('Failed to deserialize');
});

it('writes NUL-free content when migrating session carts to the database', function (): void {
    config(['session.driver' => 'database']);
    DB::connection('testing')->getSchemaBuilder()->create('sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->text('payload')->nullable();
        $table->integer('last_activity')->default(0);
        $table->string('user_id')->nullable();
    });

    $item = new CartItem(id: '1', name: 'Migrated', subtitle: '', qty: 1, price: 4.0, totalPrice: 4.0, vat: 0.0);
    DB::table('sessions')->insert([
        'id' => 'sess-1',
        'payload' => base64_encode(serialize(['cart.default' => collect([$item->rowId => $item])])),
    ]);

    $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--force' => true])
        ->assertExitCode(0);

    $content = Coerce::string(DB::table('cart')->where('identifier', 'sess-1')->value('content'));
    $decoded = CartContentCodec::decode($content);

    expect($content)->not->toContain("\0")
        ->and($decoded)->toBeInstanceOf(Collection::class)
        ->and($decoded?->first()?->name)->toBe('Migrated');
});

it('decodes an empty payload as an empty cart', function (?string $payload): void {
    expect(CartContentCodec::decode($payload))->toBeInstanceOf(Collection::class)->toBeEmpty();
})->with(['null' => null, 'empty string' => '']);

it('round-trips the values a cart legitimately holds', function (): void {
    $item = new CartItem(id: '1', name: 'Typed', subtitle: '', qty: 1, price: 1.0, totalPrice: 1.0, vat: 0.0);
    $item->model = new CodecModel(['name' => 'Model']);
    $item->options = new CartItemOptions(['type' => CouponType::Fixed]);

    $decoded = CartContentCodec::decode(CartContentCodec::encode(collect([$item->rowId => $item])));
    $restored = $decoded?->first();

    expect($restored?->model)->toBeInstanceOf(CodecModel::class)
        ->and($restored?->model instanceof CodecModel ? $restored->model->getAttribute('name') : null)->toBe('Model')
        ->and($restored?->options->get('type'))->toBe(CouponType::Fixed)
        ->and($restored?->createdAt->equalTo($item->createdAt))->toBeTrue();
});

it('returns null for payloads naming unknown classes or failing to wake up', function (): void {
    $unknown = CartContentCodec::PREFIX.base64_encode('O:13:"Missing\\Klass":0:{}');
    $exploding = CartContentCodec::encode(collect([new ExplodingBuyable]));

    expect(CartContentCodec::decode($unknown))->toBeNull()
        ->and(CartContentCodec::decode($exploding))->toBeNull();
});
