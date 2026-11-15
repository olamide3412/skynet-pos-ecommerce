import { computed, reactive } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { useCartDrawerStore } from '@/Stores/cartDrawerStore';

const updatingItem = reactive({});
const addingProduct = reactive({});

export function useCart() {
  const page = usePage();
  const toast = useToast();
  const cartDrawer = useCartDrawerStore();

  const cart = computed(() => page.props.cart || { id: null, items: [], total: 0, count: 0 });
  const cartCount = computed(() => page.props.cart_count ?? cart.value?.count ?? 0);
  const cartItems = computed(() => cart.value?.items || []);

  // Map of productId -> first matching cart item (for quick card lookups)
  const cartItemsByProductId = computed(() => {
    const map = {};
    for (const item of cartItems.value) {
      if (!map[item.product_id]) {
        map[item.product_id] = item;
      }
    }
    return map;
  });

  // Map of listing_key ("p{id}-u{unit_id}" or "p{id}") -> matching cart item
  const cartItemsByListingKey = computed(() => {
    const map = {};
    for (const item of cartItems.value) {
      const key = item.product_pos_unit_id ? `p${item.product_id}-u${item.product_pos_unit_id}` : `p${item.product_id}`;
      if (!map[key]) {
        map[key] = item;
      }
    }
    return map;
  });

  const getCartItemForProduct = (productId) => {
    return cartItemsByProductId.value[productId] || null;
  };

  const getCartItemForListing = (productOrKey) => {
    if (!productOrKey) return null;
    if (typeof productOrKey === 'string') {
      return cartItemsByListingKey.value[productOrKey] || null;
    }
    const hasSpecificUnit = Boolean(productOrKey.unit_id || productOrKey.product_pos_unit_id);
    const key = productOrKey.listing_key 
      || (productOrKey.unit_id ? `p${productOrKey.id}-u${productOrKey.unit_id}` : null)
      || (productOrKey.product_pos_unit_id ? `p${productOrKey.id}-u${productOrKey.product_pos_unit_id}` : null)
      || `p${productOrKey.id}`;

    // Direct exact match by listing key
    if (cartItemsByListingKey.value[key]) {
      return cartItemsByListingKey.value[key];
    }

    // Only fallback to productId lookup if this is a general base product without a specific packaging unit
    if (!hasSpecificUnit && (!productOrKey.listing_key || productOrKey.listing_key === `p${productOrKey.id}`)) {
      return cartItemsByListingKey.value[`p${productOrKey.id}`] || cartItemsByProductId.value[productOrKey.id] || null;
    }

    return null;
  };

  const addToCart = (product, quantity = 1, options = {}) => {
    const productId = product.id;
    const unitId = options.product_pos_unit_id ?? (product.unit_id || null);
    const key = product.listing_key 
      || (unitId ? `p${productId}-u${unitId}` : null)
      || `p${productId}`;

    addingProduct[key] = true;
    addingProduct[productId] = true;

    const payload = {
      product_id: productId,
      quantity: quantity,
      product_pos_unit_id: unitId,
      variant: options.variant ?? null,
      variant_key: options.variant_key ?? null,
    };

    useForm(payload).post(route('cart.store'), {
      preserveScroll: true,
      onSuccess: () => {
        if (options.openDrawer) {
          cartDrawer.open();
        }
      },
      onError: (errors) => {
        const msg = Object.values(errors)[0] || 'Could not add to cart.';
        toast.error(msg);
      },
      onFinish: () => {
        addingProduct[key] = false;
        addingProduct[productId] = false;
      },
    });
  };

  const updateQuantity = (cartItem, newQuantity) => {
    if (!cartItem) return;
    const itemId = cartItem.id;
    updatingItem[itemId] = true;

    if (newQuantity < 1) {
      router.delete(route('cart.destroy', itemId), {
        preserveScroll: true,
        onError: () => {
          toast.error('Could not remove item.');
        },
        onFinish: () => {
          updatingItem[itemId] = false;
        },
      });
      return;
    }

    router.put(route('cart.update', itemId), { quantity: newQuantity }, {
      preserveScroll: true,
      onError: () => {
        toast.error('Could not update quantity.');
      },
      onFinish: () => {
        updatingItem[itemId] = false;
      },
    });
  };

  const removeFromCart = (cartItem) => {
    if (!cartItem) return;
    updateQuantity(cartItem, 0);
  };

  const isUpdatingItem = (itemId) => Boolean(updatingItem[itemId]);
  const isAddingProduct = (productOrId) => {
    if (!productOrId) return false;
    if (typeof productOrId === 'object') {
      const key = productOrId.listing_key 
        || (productOrId.unit_id ? `p${productOrId.id}-u${productOrId.unit_id}` : null)
        || (productOrId.product_pos_unit_id ? `p${productOrId.id}-u${productOrId.product_pos_unit_id}` : null)
        || `p${productOrId.id}`;
      return Boolean(addingProduct[key] || (key === `p${productOrId.id}` && addingProduct[productOrId.id]));
    }
    return Boolean(addingProduct[productOrId]);
  };

  return {
    cart,
    cartCount,
    cartItems,
    cartItemsByProductId,
    cartItemsByListingKey,
    getCartItemForProduct,
    getCartItemForListing,
    addToCart,
    updateQuantity,
    removeFromCart,
    isUpdatingItem,
    isAddingProduct,
  };
}
