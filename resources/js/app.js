const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

document.querySelectorAll('[data-pos]').forEach((root) => {
    const productsElement = root.querySelector('[data-pos-products]');
    const heldCartsElement = root.querySelector('[data-held-carts]');
    const packagesElement = root.querySelector('[data-pos-packages]');
    const products = JSON.parse(productsElement?.textContent || '[]');
    const heldCarts = JSON.parse(heldCartsElement?.textContent || '[]');
    const servicePackages = JSON.parse(packagesElement?.textContent || '[]');
    const productsById = new Map(products.map((product) => [String(product.id), product]));
    const heldCartsById = new Map(heldCarts.map((heldCart) => [String(heldCart.id), heldCart]));
    const packagesById = new Map(servicePackages.map((servicePackage) => [String(servicePackage.id), servicePackage]));
    const cart = [];
    let paymentTouched = false;

    const searchInput = root.querySelector('[data-product-search]');
    const productButtons = [...root.querySelectorAll('[data-add-product]')];
    const packageButtons = [...root.querySelectorAll('[data-add-package]')];
    const cartItems = root.querySelector('[data-cart-items]');
    const emptyCart = root.querySelector('[data-empty-cart]');
    const cartCount = root.querySelector('[data-cart-count]');
    const cartTotal = root.querySelector('[data-cart-total]');
    const paymentAmount = root.querySelector('[data-payment-amount]');
    const paymentMethod = root.querySelector('[data-payment-method]');
    const changeTotal = root.querySelector('[data-change-total]');
    const submitButton = root.querySelector('[data-submit-checkout]');
    const holdButton = root.querySelector('[data-hold-cart]');
    const holdForm = root.querySelector('[data-hold-cart-form]');

    const total = () => cart.reduce((sum, item) => sum + item.quantity * item.unitPrice, 0);

    const normalizeInteger = (value, fallback = 0) => {
        const parsedValue = Number.parseInt(value, 10);

        return Number.isFinite(parsedValue) ? parsedValue : fallback;
    };

    const syncPayment = () => {
        const currentTotal = total();

        if (! paymentTouched) {
            paymentAmount.value = currentTotal > 0 ? currentTotal : '';
        }

        const paid = normalizeInteger(paymentAmount.value);
        const difference = paid - currentTotal;
        changeTotal.textContent = rupiah.format(difference);
        changeTotal.classList.toggle('text-red-700', difference < 0);
        changeTotal.classList.toggle('text-emerald-700', difference >= 0);
    };

    const renderCart = () => {
        cartItems.innerHTML = cart.map((item, index) => `
            <div class="rounded-md border border-zinc-200 p-3" data-cart-row="${item.productId}">
                <input type="hidden" name="items[${index}][product_id]" value="${item.productId}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-zinc-950">${escapeHtml(item.name)}</p>
                        <p class="text-xs text-zinc-500">${rupiah.format(item.unitPrice)} / ${escapeHtml(item.unit)}</p>
                    </div>
                    <button type="button" data-remove-item="${item.productId}" class="rounded-md border border-red-200 px-2 py-1 text-xs font-semibold text-red-700">Hapus</button>
                </div>
                <div class="mt-3 grid grid-cols-[auto_1fr_auto] items-center gap-2">
                    <button type="button" data-decrease-item="${item.productId}" class="h-9 w-9 rounded-md border border-zinc-300 text-lg font-semibold">-</button>
                    <input name="items[${index}][quantity]" data-cart-quantity="${item.productId}" type="number" min="1" value="${item.quantity}" class="h-9 w-full rounded-md border-zinc-300 text-center text-sm font-semibold">
                    <button type="button" data-increase-item="${item.productId}" class="h-9 w-9 rounded-md border border-zinc-300 text-lg font-semibold">+</button>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <label class="text-xs font-medium text-zinc-600">
                        Harga
                        <input name="items[${index}][unit_price]" data-cart-price="${item.productId}" type="number" min="0" value="${item.unitPrice}" class="mt-1 w-full rounded-md border-zinc-300 text-sm">
                    </label>
                    <label class="text-xs font-medium text-zinc-600">
                        Sumber file
                        <input name="items[${index}][source_note]" data-cart-source="${item.productId}" value="${escapeHtml(item.sourceNote)}" class="mt-1 w-full rounded-md border-zinc-300 text-sm" placeholder="USB/WA">
                    </label>
                </div>
                <div class="mt-3 flex justify-between text-sm">
                    <span class="text-zinc-500">Subtotal</span>
                    <strong>${rupiah.format(item.quantity * item.unitPrice)}</strong>
                </div>
            </div>
        `).join('');

        emptyCart.hidden = cart.length > 0;
        cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartTotal.textContent = rupiah.format(total());
        submitButton.disabled = cart.length === 0;
        holdButton.disabled = cart.length === 0;
        syncPayment();
    };

    const addCartItem = (product, quantity = 1, unitPrice = product.price, sourceNote = '') => {
        const existingItem = cart.find((item) => item.productId === String(product.id));

        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            cart.push({
                productId: String(product.id),
                name: product.name,
                unit: product.unit,
                quantity,
                unitPrice,
                sourceNote,
            });
        }
    };

    const addProduct = (productId) => {
        const product = productsById.get(String(productId));

        if (! product) {
            return;
        }

        addCartItem(product);

        renderCart();
    };

    const addPackage = (packageId) => {
        const servicePackage = packagesById.get(String(packageId));

        if (! servicePackage) {
            return;
        }

        servicePackage.items.forEach((item) => {
            const product = productsById.get(String(item.product_id));

            if (! product) {
                return;
            }

            addCartItem(product, normalizeInteger(item.quantity, 1), normalizeInteger(item.unit_price, product.price), servicePackage.name);
        });

        renderCart();
    };

    productButtons.forEach((button) => {
        button.addEventListener('click', () => {
            addProduct(button.dataset.productId);
        });
    });

    packageButtons.forEach((button) => {
        button.addEventListener('click', () => {
            addPackage(button.dataset.packageId);
        });
    });

    searchInput?.addEventListener('input', () => {
        const keyword = searchInput.value.trim().toLowerCase();

        productButtons.forEach((button) => {
            button.hidden = keyword !== '' && ! button.dataset.productName.includes(keyword);
        });
    });

    cartItems.addEventListener('click', (event) => {
        const target = event.target.closest('button');

        if (! target) {
            return;
        }

        const action = target.dataset;
        const productId = action.increaseItem || action.decreaseItem || action.removeItem;
        const item = cart.find((cartItem) => cartItem.productId === productId);

        if (! item) {
            return;
        }

        if (action.increaseItem) {
            item.quantity += 1;
        }

        if (action.decreaseItem) {
            item.quantity = Math.max(1, item.quantity - 1);
        }

        if (action.removeItem) {
            cart.splice(cart.indexOf(item), 1);
        }

        renderCart();
    });

    cartItems.addEventListener('change', (event) => {
        const target = event.target;
        const productId = target.dataset.cartQuantity || target.dataset.cartPrice || target.dataset.cartSource;
        const item = cart.find((cartItem) => cartItem.productId === productId);

        if (! item) {
            return;
        }

        if (target.dataset.cartQuantity) {
            item.quantity = Math.max(1, normalizeInteger(target.value, 1));
        }

        if (target.dataset.cartPrice) {
            item.unitPrice = Math.max(0, normalizeInteger(target.value));
        }

        if (target.dataset.cartSource) {
            item.sourceNote = target.value;
        }

        renderCart();
    });

    root.querySelector('[data-clear-cart]')?.addEventListener('click', () => {
        cart.splice(0, cart.length);
        paymentTouched = false;
        renderCart();
    });

    root.querySelectorAll('[data-restore-held-cart]').forEach((button) => {
        button.addEventListener('click', () => {
            const heldCart = heldCartsById.get(button.dataset.restoreHeldCart);

            if (! heldCart) {
                return;
            }

            cart.splice(0, cart.length, ...heldCart.items.map((item) => {
                const product = productsById.get(String(item.product_id));

                return {
                    productId: String(item.product_id),
                    name: product?.name ?? item.product_name,
                    unit: product?.unit ?? item.unit,
                    quantity: normalizeInteger(item.quantity, 1),
                    unitPrice: normalizeInteger(item.unit_price),
                    sourceNote: item.source_note ?? '',
                };
            }));

            paymentTouched = false;
            renderCart();
            root.querySelector('[data-checkout-form]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    holdButton?.addEventListener('click', () => {
        if (cart.length === 0 || ! holdForm) {
            return;
        }

        const draftName = window.prompt('Nama draft keranjang', `Draft ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`);

        if (! draftName) {
            return;
        }

        holdForm.querySelectorAll('[data-draft-field]').forEach((field) => field.remove());

        const addField = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            input.dataset.draftField = 'true';
            holdForm.append(input);
        };

        addField('name', draftName);

        cart.forEach((item, index) => {
            addField(`items[${index}][product_id]`, item.productId);
            addField(`items[${index}][quantity]`, item.quantity);
            addField(`items[${index}][unit_price]`, item.unitPrice);
            addField(`items[${index}][source_note]`, item.sourceNote);
        });

        holdForm.requestSubmit();
    });

    root.querySelectorAll('[data-quick-cash]').forEach((button) => {
        button.addEventListener('click', () => {
            paymentTouched = true;
            paymentAmount.value = button.dataset.quickCash;
            syncPayment();
        });
    });

    paymentAmount.addEventListener('input', () => {
        paymentTouched = true;
        syncPayment();
    });

    paymentMethod.addEventListener('change', () => {
        if (paymentMethod.value === 'qris') {
            paymentTouched = false;
        }

        syncPayment();
    });

    root.querySelector('[data-checkout-form]')?.addEventListener('submit', (event) => {
        if (cart.length === 0 || normalizeInteger(paymentAmount.value) < total()) {
            event.preventDefault();
            syncPayment();
        }
    });

    renderCart();
});
