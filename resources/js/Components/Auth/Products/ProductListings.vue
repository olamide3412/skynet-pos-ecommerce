<script setup>
import { reactive, watch } from 'vue';
import ProductListing from './ProductListing.vue';
import Pagination from '../../Pagination.vue';
import { debounce } from 'lodash';
import { router } from '@inertiajs/vue3';
import SelectInput from '../../Forms/SelectInput.vue';
import TextInput from '../../Forms/TextInput.vue';

const props = defineProps({
    products: {
        type: Object,
        required: true
    },
    searchTerm: String,
    orderBy: {
        type: String,
        default: 'id'
    },
    orderDir: {
        type: String,
        default: 'asc'
    }
})

const params = reactive({
    search : props.searchTerm,
    orderBy : props.orderBy,
    orderDir : props.orderDir
})

watch(params,
    debounce((params) =>
        router.get(
            route('product.index'),
            { search: params.search, orderBy: params.orderBy, orderDir: params.orderDir },
            { preserveState: true }
        ),
        1000
    )
);
</script>

<template>
    <div>
        <!-- Filters -->
        <div class="md:flex md:justify-end md:items-center md:space-x-2 mb-4">
            <div class="w-full md:w-1/4">
                <TextInput
                    type="search"
                    name="Search"
                    v-model="params.search"
                    :required="false"
                    label="Search Products"
                />
            </div>
            <div class="w-full md:w-1/4">
                <SelectInput name="Order By" v-model="params.orderBy"
                    :options="[
                        { value: 'id', label: 'Id' },
                        { value: 'name', label: 'Name' },
                        { value: 'price', label: 'Price' }
                    ]"
                />
            </div>
            <div class="w-full md:w-1/4">
                <SelectInput name="Order Dir" v-model="params.orderDir"
                    :options="[
                        { value: 'asc', label: 'ASC' },
                        { value: 'desc', label: 'DESC' }
                    ]"
                />
            </div>
        </div>

        <!-- Listings -->
        <ProductListing
            v-for="(product, index) in products.data"
            :key="product.id"
            :product="product"
            :index="index"
            :currentPage="products.current_page"
            :perPage="products.per_page"
        />

        <!-- Pagination -->
        <div>
            <Pagination :paginator="products"/>
        </div>
    </div>
</template>
