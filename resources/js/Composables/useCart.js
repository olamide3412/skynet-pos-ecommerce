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

  const getCartItemForProduct = (productId) => {
    return cartItemsByProductId.value[productId] || null;
  };

  const addToCart = (product, quantity = 1, options = {}) => {
    const productId = product.id;
    addingProduct[productId] = true;

    const payload = {
      product_id: productId,
      quantity: quantity,
      product_pos_unit_id: options.product_pos_unit_id ?? null,
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
  const isAddingProduct = (productId) => Boolean(addingProduct[productId]);

  return {
    cart,
    cartCount,
    cartItems,
    cartItemsByProductId,
    getCartItemForProduct,
    addToCart,
    updateQuantity,
    removeFromCart,
    isUpdatingItem,
    isAddingProduct,
  };
}
